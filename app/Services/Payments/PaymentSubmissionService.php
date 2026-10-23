<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\PaymentException;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingStatusService;
use App\Services\Booking\BookingTokenResolver;
use App\Services\Media\MediaService;
use App\Settings\PricingSettings;
use Illuminate\Support\Facades\DB;

/**
 * The renter's side of paying (M9.2–M9.4, PRD P-1–P-3). They pick a method and send the
 * transaction message, with a proof if they like; cash means paying at the office. The hold
 * then follows D38: a payment being checked keeps the car until staff act, cash keeps it for
 * `cash_hold_hours`.
 */
class PaymentSubmissionService
{
    public function __construct(
        private readonly BookingStatusService $statuses,
        private readonly AvailabilityService $availability,
        private readonly MediaService $media,
        private readonly PricingSettings $pricing,
    ) {}

    public function submit(Booking $booking, PaymentMethod $method, ?string $message, ?string $proofUuid = null): PaymentSubmission
    {
        if (! $method->is_active) {
            throw PaymentException::methodUnavailable();
        }

        return DB::transaction(function () use ($booking, $method, $message, $proofUuid) {
            // A double click submits twice: the second waits here, then finds it submitted.
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->acceptsPayment()) {
                throw PaymentException::notAwaitingPayment($locked);
            }

            if ($locked->payments()->where('status', PaymentStatus::Pending)->exists()) {
                throw PaymentException::alreadySubmitted();
            }

            $isCash = $method->code === PaymentMethodCode::Cash;
            $proof = $proofUuid !== null && ! $isCash
                ? $this->media->attach($booking, BookingTokenResolver::PAYMENT_PROOF, $proofUuid, actor: $booking)
                : null;

            $submission = $booking->payments()->create([
                'payment_method_code' => $method->code,
                'transaction_message' => $isCash ? null : $message,
                'proof_media_id' => $proof?->id,
            ]);

            $booking->status = $locked->status;

            if ($isCash) {
                $this->statuses->transition($booking, BookingStatus::AwaitingCash, 'renter');
                $this->availability->extendHold($booking, now()->addHours($this->pricing->cash_hold_hours));
            } else {
                $this->statuses->transition($booking, BookingStatus::PaymentSubmitted, 'renter');
                $this->availability->extendHold($booking, null);
            }

            return $submission;
        });
    }
}
