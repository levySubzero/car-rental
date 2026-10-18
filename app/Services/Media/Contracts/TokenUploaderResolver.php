<?php

namespace App\Services\Media\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Turns the plain token a renter sends in X-Booking-Token into the model it grants
 * access to. M8 binds the Booking implementation; until then NullTokenUploaderResolver
 * rejects every token.
 */
interface TokenUploaderResolver
{
    public function resolve(string $plainToken): ?Model;
}
