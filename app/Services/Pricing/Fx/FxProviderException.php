<?php

namespace App\Services\Pricing\Fx;

use RuntimeException;

/**
 * The rate source failed or sent data that does not pass validation. Nothing is stored.
 */
class FxProviderException extends RuntimeException
{
    public static function missingKey(string $provider): self
    {
        return new self("No API key is configured for {$provider} (FX_API_KEY).");
    }

    public static function request(string $provider, string $reason): self
    {
        return new self("{$provider} request failed: {$reason}");
    }

    public static function invalid(string $provider, string $reason): self
    {
        return new self("{$provider} returned unusable data: {$reason}");
    }
}
