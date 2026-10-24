<?php

namespace App\Http\Middleware;

use App\Services\Payments\Gateways\DarajaClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daraja signs nothing, so its callbacks are trusted only on a URL with our secret in it
 * (DARAJA_CALLBACK_SECRET) and, when DARAJA_ALLOWED_IPS is set, from Safaricom's addresses
 * (M11B.5, D42). Anything else gets a plain 404, as if the route did not exist.
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

        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            abort(404);
        }

        return $next($request);
    }
}
