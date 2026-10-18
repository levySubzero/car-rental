<?php

namespace App\Services\Media;

use App\Services\Media\Contracts\TokenUploaderResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Default until bookings exist (M8): no token is valid.
 */
class NullTokenUploaderResolver implements TokenUploaderResolver
{
    public function resolve(string $plainToken): ?Model
    {
        return null;
    }
}
