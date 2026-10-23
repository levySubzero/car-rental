<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Services\Media\Contracts\TokenUploaderResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Lets a renter upload with their booking token: KYC documents while the booking is a draft
 * (M8.12), and a payment proof while it waits for payment (M9.3). Once submitted, the
 * documents are fixed.
 */
class BookingTokenResolver implements TokenUploaderResolver
{
    public const PAYMENT_PROOF = 'booking.payment_proof';

    public function __construct(private readonly BookingTokenService $tokens) {}

    public function resolve(string $plainToken): ?Booking
    {
        $booking = $this->tokens->find($plainToken);

        return $booking !== null && ($booking->isDraft() || $booking->status->acceptsPayment()) ? $booking : null;
    }

    public function canUpload(Model $owner, string $collection): bool
    {
        if (! $owner instanceof Booking) {
            return false;
        }

        return $collection === self::PAYMENT_PROOF ? $owner->status->acceptsPayment() : $owner->isDraft();
    }
}
