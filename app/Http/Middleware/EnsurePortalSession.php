<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The renter portal's sign-in (M10B): the email or phone whose code was confirmed, kept in
 * the session for an hour. Without it, back to the portal's start.
 */
class EnsurePortalSession
{
    public const SESSION_KEY = 'portal';

    public const MINUTES = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (self::identity($request) === null) {
            $request->session()->forget(self::SESSION_KEY);

            return to_route('portal.index');
        }

        return $next($request);
    }

    /**
     * @return array{type: 'email'|'phone', value: string}|null
     */
    public static function identity(Request $request): ?array
    {
        /** @var array{type: 'email'|'phone', value: string, expires_at: int}|null $session */
        $session = $request->session()->get(self::SESSION_KEY);

        if ($session === null || $session['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return ['type' => $session['type'], 'value' => $session['value']];
    }
}
