<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Services\Media\Contracts\TokenUploaderResolver;

/**
 * Lets a renter upload KYC documents with their booking token (M8.12), while the booking is
 * still a draft. Once submitted, its documents are fixed.
 */
class BookingTokenResolver implements TokenUploaderResolver
{
    public function __construct(private readonly BookingTokenService $tokens) {}

    public function resolve(string $plainToken): ?Booking
    {
        $booking = $this->tokens->find($plainToken);

        return $booking?->isDraft() ? $booking : null;
    }
}
