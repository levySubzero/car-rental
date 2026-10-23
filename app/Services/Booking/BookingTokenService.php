<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Illuminate\Support\Str;

/**
 * The renter's secret link to their booking (M8.5). The plain token is shown once, in the
 * URL; only its SHA-256 hash is stored.
 */
class BookingTokenService
{
    /**
     * A new plain token and the hash to store.
     *
     * @return array{token: string, hash: string}
     */
    public function issue(): array
    {
        $token = Str::random((int) config('baines.booking_token_length'));

        return ['token' => $token, 'hash' => $this->hash($token)];
    }

    public function find(string $plainToken): ?Booking
    {
        if (strlen($plainToken) !== (int) config('baines.booking_token_length')) {
            return null;
        }

        return Booking::query()->where('access_token_hash', $this->hash($plainToken))->first();
    }

    /**
     * Replace the token, so an old link stops working. Returns the new plain token (M11.5).
     */
    public function rotate(Booking $booking): string
    {
        ['token' => $token, 'hash' => $hash] = $this->issue();

        $booking->forceFill(['access_token_hash' => $hash])->save();

        return $token;
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
