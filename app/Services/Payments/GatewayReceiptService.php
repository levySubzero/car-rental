<?php

namespace App\Services\Payments;

use App\Enums\GatewayTransactionStatus;
use App\Exceptions\Domain\PaymentException;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Online money staff must deal with by hand (M11B.9, D42): paybill receipts whose account
 * number named no booking, and payments that could not be applied safely. Staff attach one to
 * the right booking (settled as if it had matched, with them as the actor), or close it as
 * refunded or with a note. Nothing here moves money: refunds are paid back outside the system.
 */
class GatewayReceiptService
{
    public const LOG_NAME = 'payments';

    public function __construct(private readonly OnlinePaymentService $payments) {}

    /**
     * Waiting for staff first by default, oldest first; otherwise every receipt, newest first.
     * Search by receipt, account number, payer name or booking reference.
     *
     * @param  array{scope?: string|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, GatewayTransaction>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $open = ($filters['scope'] ?? 'open') === 'open';

        return GatewayTransaction::query()
            ->with(['booking:id,reference,status', 'resolver:id,name', 'submission:id,booking_id'])
            ->when($open, fn (Builder $query) => $query->awaitingStaff()->oldest(), fn (Builder $query) => $query
                ->whereNot('status', GatewayTransactionStatus::Initiated)
                ->latest())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('receipt', 'like', "%{$search}%")
                    ->orWhere('external_ref', 'like', "%{$search}%")
                    ->orWhere('payer_name', 'like', "%{$search}%")
                    ->orWhere('payload->bill_ref', 'like', "%{$search}%")
                    ->orWhereHas('booking', fn (Builder $query) => $query->where('reference', 'like', "%{$search}%")),
            ))
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function openCount(): int
    {
        return GatewayTransaction::query()->awaitingStaff()->count();
    }

    /**
     * Apply the money to `$booking` as its reservation payment. When it still cannot be
     * applied (the booking no longer takes a payment, too little was paid...), it stays for
     * staff with the new reason.
     */
    public function attach(GatewayTransaction $transaction, Booking $booking, User $actor): GatewayTransaction
    {
        $transaction = DB::transaction(function () use ($transaction, $booking) {
            $locked = $this->lockOpen($transaction);
            $locked->forceFill(['booking_id' => $booking->id])->save();

            return $locked;
        });

        $transaction = $this->payments->settle($transaction->setRelation('booking', $booking), $actor);

        if ($transaction->status === GatewayTransactionStatus::Matched) {
            $transaction->forceFill(['resolved_by' => $actor->id, 'resolved_at' => now()])->save();

            activity(self::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties(['gateway_transaction' => $transaction->id, 'receipt' => $transaction->receipt])
                ->event('receipt-attached')
                ->log('online payment attached to booking');
        }

        return $transaction;
    }

    /**
     * Close a receipt without applying it: refunded to the payer, or closed with a note (a
     * duplicate, a payment for something else).
     */
    public function resolve(GatewayTransaction $transaction, GatewayTransactionStatus $outcome, string $note, User $actor): GatewayTransaction
    {
        if (! in_array($outcome, [GatewayTransactionStatus::Refunded, GatewayTransactionStatus::Ignored], true)) {
            throw new InvalidArgumentException("A receipt cannot be resolved as {$outcome->value}.");
        }

        return DB::transaction(function () use ($transaction, $outcome, $note, $actor) {
            $locked = $this->lockOpen($transaction);

            $locked->forceFill([
                'status' => $outcome,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'resolution_note' => trim($note),
            ])->save();

            activity(self::LOG_NAME)->performedOn($locked)->causedBy($actor)
                ->withProperties(['outcome' => $outcome->value, 'note' => $locked->resolution_note, 'booking' => $locked->booking?->reference])
                ->event('receipt-'.$outcome->value)
                ->log("online payment {$outcome->label()}");

            return $locked;
        });
    }

    private function lockOpen(GatewayTransaction $transaction): GatewayTransaction
    {
        $locked = GatewayTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

        if (! $locked->status->awaitsStaff()) {
            throw PaymentException::receiptAlreadyResolved();
        }

        return $locked;
    }
}
