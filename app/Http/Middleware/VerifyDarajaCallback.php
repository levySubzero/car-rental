<?php

namespace App\Http\Middleware;

use App\Services\Payments\Gateways\DarajaClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daraja signs nothing, so its callbacks are trusted only on a URL with our secret in it
 * (DARAJA_CALLBACK_SECRET) and from Safaricom's addresses in DARAJA_ALLOWED_IPS (addresses
 * or CIDR ranges) (M11B.5, D42). The allowlist is optional in the sandbox but required with
 * DARAJA_ENV=production, where a C2B confirmation settles a booking on its word (M12.16).
 * Anything else gets a plain 404, as if the route did not exist.
 */
class VerifyDarajaCallback
{
    public function __construct(private readonly DarajaClient $daraja) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->daraja->callbackSecret();
        $given = $request->route('secret');

        if (blank($secret) || ! is_string($given) || ! hash_equals($secret, $given)) {
            abort(404);
        }

        $allowed = $this->daraja->allowedIps();

        if ($allowed === [] && config('services.daraja.env') === 'production') {
            Log::error('Daraja callback refused: DARAJA_ALLOWED_IPS is required with DARAJA_ENV=production');

            abort(404);
        }

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            Log::warning('Daraja callback from an address not in DARAJA_ALLOWED_IPS', ['ip' => $request->ip()]);

            abort(404);
        }

        return $next($request);
    }
}
