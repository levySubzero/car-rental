<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Cloudflare Turnstile token (the "cf-turnstile-response" field).
 */
class Turnstile implements ValidationRule
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // Cloudflare's documented always-pass secret, used in local development.
    public const TEST_SECRET = '1x0000000000000000000000000000000AA';

    // Run even when the token is missing or empty.
    public bool $implicit = true;

    public function __construct(private readonly ?string $ip = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('services.turnstile.secret_key');

        if ($secret === self::TEST_SECRET && ! app()->isProduction()) {
            return;
        }

        if (! is_string($value) || $value === '' || $secret === '') {
            $fail('Please complete the security check.');

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => $secret,
                'response' => $value,
                'remoteip' => $this->ip,
            ]));

            if ($response->json('success') === true) {
                return;
            }
        } catch (Throwable $exception) {
            Log::warning('Turnstile verification failed', ['error' => $exception->getMessage()]);
        }

        $fail('The security check failed. Please try again.');
    }
}
