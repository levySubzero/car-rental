<?php

namespace App\Services\Payments\Gateways;

use RuntimeException;

/**
 * A payment gateway could not be reached, refused the request or answered with something we
 * cannot use (M11B). Messages name the gateway and its reason, never a key or token.
 */
class GatewayException extends RuntimeException
{
    public static function notConfigured(string $gateway): self
    {
        return new self("{$gateway} is not configured: its keys are missing from the environment.");
    }

    public static function request(string $gateway, string $reason): self
    {
        return new self("{$gateway} request failed: {$reason}");
    }

    public static function invalid(string $gateway, string $reason): self
    {
        return new self("{$gateway} returned unusable data: {$reason}");
    }

    public static function invalidInput(string $gateway, string $reason): self
    {
        return new self("{$gateway} cannot take this request: {$reason}");
    }
}
