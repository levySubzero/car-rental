<?php

namespace App\Services\Media\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Turns the plain token a renter sends in X-Booking-Token into the model it grants
 * access to. Bound to BookingTokenResolver.
 */
interface TokenUploaderResolver
{
    public function resolve(string $plainToken): ?Model;
}
