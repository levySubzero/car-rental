<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Media\Contracts\TokenUploaderResolver;
use App\Services\Media\CurrentUploader;
use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The media routes serve staff and renters, so they sit outside the admin middleware.
 * This decides who the caller is (CurrentUploader) for MediaPolicy:
 *
 * - an X-Booking-Token header makes the caller the model the token resolves to;
 * - otherwise a signed-in user counts as staff only if they would pass EnsureStaff
 *   and EnsureTwoFactor, so /media is no way around either.
 */
class ResolveMediaUploader
{
    public const TOKEN_HEADER = 'X-Booking-Token';

    public function __construct(
        private readonly CurrentUploader $uploader,
        private readonly TokenUploaderResolver $tokens,
        private readonly SecuritySettings $security,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header(self::TOKEN_HEADER);

        if (is_string($token) && $token !== '') {
            $this->uploader->setTokenOwner($this->tokens->resolve($token));

            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User
            && $user->canAccessAdmin()
            && (! $this->security->require_two_factor || $user->hasConfirmedTwoFactor())) {
            $this->uploader->setStaff($user);
        }

        return $next($request);
    }
}
