<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs staff out after config('baines.admin_idle_minutes') without an admin request.
 */
class AdminIdleTimeout
{
    public const SESSION_KEY = 'admin.last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $lastActivity = $session->get(self::SESSION_KEY);
        $limit = (int) config('baines.admin_idle_minutes') * 60;

        if (is_int($lastActivity) && now()->getTimestamp() - $lastActivity > $limit) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return to_route('login')->with('status', 'You were signed out after a period of inactivity.');
        }

        $session->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
