<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * When SecuritySettings::require_two_factor is on, staff must confirm TOTP
 * two-factor authentication before reaching the admin.
 */
class EnsureTwoFactor
{
    public function __construct(private readonly SecuritySettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($this->settings->require_two_factor && $user instanceof User && ! $user->hasConfirmedTwoFactor()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Set up two-factor authentication to continue to the admin.']);

            return to_route('security.edit');
        }

        return $next($request);
    }
}
