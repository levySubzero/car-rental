<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only active staff accounts may use the admin and the account settings pages.
 * Deactivated accounts are already signed out by EnsureActiveAccount.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canAccessAdmin(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
