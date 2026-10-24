<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Exceptions\Domain\PaymentException;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingStatusService;
use App\Settings\PricingSettings;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Staff checking a renter's payment against their statement (M9.5, PRD P-4). Verifying
 * reserves the booking and firm-blocks the car; less than the reservation needs
 * payments.accept-partial and a note, and the shortfall moves to the balance (D38).
 * Rejecting gives the renter a fresh hold to try again. Online payments (M11B) are verified
 * here too, by the system.
 */
class PaymentVerificationService
{
    public const LOG_NAME = 'payments';

    public function __construct(
        private readonly BookingStatusService $statuses,
        private readonly AvailabilityService $availability,
        private readonly PricingSettings $pricing,
    ) {}

    /**
     * Oldest first, so the longest wait is checked first. Search by booking reference,
     * renter name or phone, or the transaction message.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, PaymentSubmission>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return PaymentSubmission::query()
            ->with(['booking.car:id,name', 'booking.driver', 'method', 'proof', 'gatewayTransaction', 'verifier:id,name', 'rejecter:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('transaction_message', 'like', "%{$search}%")
                    ->orWhereHas('booking', fn (Builder $query) => $query
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('driver', fn (Builder $query) => $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))),
            ))
            ->when(($filters['status'] ?? null) === PaymentStatus::Pending->value, fn (Builder $query) => $query->oldest(), fn (Builder $query) => $query->latest())
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int> status => number of submissions
     */
    public function counts(): array
    {
        /** @var array<string, int> */
        return PaymentSubmission::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function verify(PaymentSubmission $submission, string $reference, Money $amount, User $actor, ?string $note = null): PaymentSubmission
    {
        if ($amount->minor <= 0) {
            throw PaymentException::amountNotPositive();
        }

        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        return DB::transaction(function () use ($submission, $reference, $amount, $actor, $note) {
            [$submission, $booking] = $this->lock($submission);

            $expected = $submission->isCash() ? BookingStatus::AwaitingCash : BookingStatus::PaymentSubmitted;

            if ($booking->status !== $expected) {
                throw PaymentException::notAwaitingPayment($booking);
            }

            $partial = $amount->minor < (int) $booking->reservation_minor;

            if ($partial && ! $actor->can(Permission::PaymentsAcceptPartial)) {
                throw PaymentException::partialNotAllowed();
            }

            if ($partial && $note === null) {
                throw PaymentException::partialNeedsNote();
            }

            // A cash hold that lapsed before the sweep ran may have lost the car meanwhile.
            if (! $this->availability->isAvailable($booking->car, $booking->range(), $booking)) {
                throw PaymentException::carNoLongerFree();
            }

            $this->markVerified($submission, $booking, trim($reference), $amount->minor, $actor, $note, $partial ? "Part payment: {$note}" : null);

            return $submission;
        });
    }

    /**
     * An online payment the gateway confirmed (M11B, D42): it is recorded as a submission and
     * verified at once by the system, walking Payment submitted → Reserved so staff still hear
     * about it. Staff resolving a receipt by hand (M11B.9) pass themselves as the actor.
     *
     * @param  User|'system'  $actor
     */
    public function confirmOnline(Booking $booking, GatewayTransaction $transaction, User|string $actor = 'system'): PaymentSubmission
    {
        return DB::transaction(function () use ($booking, $transaction, $actor) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->status->acceptsPayment()) {
                throw PaymentException::notAwaitingPayment($booking);
            }

            if ($booking->payments()->where('status', PaymentStatus::Pending)->exists()) {
                throw PaymentException::alreadySubmitted();
            }

            if (! $this->availability->isAvailable($booking->car, $booking->range(), $booking)) {
                throw PaymentException::carNoLongerFree();
            }

            $label = $transaction->gateway->label();
            $reference = $transaction->receipt ?? $transaction->external_ref;

            $submission = $booking->payments()->create([
                'payment_method_code' => $transaction->gateway->method(),
                'transaction_message' => "{$label}: {$reference}",
            ]);
            $submission->forceFill(['gateway_transaction_id' => $transaction->id])->save();
            $submission->setRelation('gatewayTransaction', $transaction);

            $this->statuses->transition($booking, BookingStatus::PaymentSubmitted, $actor, "Paid online ({$label})");
            $this->markVerified($submission, $booking, $reference, $transaction->amount_minor, $actor, null, null, $transaction->gateway->value);

            return $submission;
        });
    }

    public function reject(PaymentSubmission $submission, string $reason, User $actor): PaymentSubmission
    {
        return DB::transaction(function () use ($submission, $reason, $actor) {
            [$submission, $booking] = $this->lock($submission);

            if ($submission->isCash()) {
                throw PaymentException::cashCannotBeRejected();
            }

            if ($booking->status !== BookingStatus::PaymentSubmitted) {
                throw PaymentException::notAwaitingPayment($booking);
            }

            $submission->forceFill([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => trim($reason),
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
            ])->save();

            $this->statuses->transition($booking, BookingStatus::PaymentRejected, $actor, $submission->rejection_reason);
            $this->availability->extendHold($booking, now()->addMinutes($this->pricing->hold_minutes));

            activity(self::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties(['submission' => $submission->id, 'reason' => $submission->rejection_reason])
                ->event('rejected')
                ->log('payment rejected');

            return $submission;
        });
    }

    /**
     * Record the verified payment, put the shortfall (if any) on the balance and reserve the
     * booking. Staff verify as themselves; online payments are verified by the system.
     *
     * @param  User|'system'  $actor
     */
    private function markVerified(PaymentSubmission $submission, Booking $booking, string $reference, int $amountMinor, User|string $actor, ?string $note, ?string $reason, ?string $source = null): void
    {
        $submission->forceFill([
            'status' => PaymentStatus::Verified,
            'reference' => $reference,
            'amount_minor' => $amountMinor,
            'note' => $note,
            'verified_by' => $actor instanceof User ? $actor->id : null,
            'verified_at' => now(),
        ])->save();

        $booking->forceFill([
            'reservation_paid_minor' => $amountMinor,
            'balance_minor' => max(0, (int) $booking->total_minor - $amountMinor),
            'paid_at' => now(),
        ])->save();

        $this->statuses->transition($booking, BookingStatus::Reserved, $actor, $reason);

        activity(self::LOG_NAME)->performedOn($booking)->causedBy($actor instanceof User ? $actor : null)
            ->withProperties([
                'submission' => $submission->id,
                'method' => $submission->payment_method_code->value,
                'reference' => $submission->reference,
                'amount_minor' => $amountMinor,
                'reservation_minor' => $booking->reservation_minor,
                'partial' => $amountMinor < (int) $booking->reservation_minor,
                'note' => $note,
                'source' => $source ?? 'staff',
            ])
            ->event('verified')
            ->log('payment verified');
    }

    /**
     * Lock the booking, then the submission, and check it is still pending: two staff
     * clicking Verify together queue up here and the second is refused.
     *
     * @return array{PaymentSubmission, Booking}
     */
    private function lock(PaymentSubmission $submission): array
    {
        $booking = Booking::query()->whereKey($submission->booking_id)->lockForUpdate()->firstOrFail();
        $locked = PaymentSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw PaymentException::alreadyHandled();
        }

        $locked->setRelation('booking', $booking);

        return [$locked, $booking];
    }
}
