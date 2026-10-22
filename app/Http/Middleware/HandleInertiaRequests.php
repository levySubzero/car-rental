<?php

namespace App\Http\Middleware;

use App\Http\Resources\Shared\AuthUserResource;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Server-side rendering is for the public site only (M7.16): the admin and the sign-in
     * pages render in the browser, where staff are already signed in.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = [
        'admin', 'admin/*',
        'settings', 'settings/*',
        'login', 'forgot-password', 'reset-password/*', 'two-factor-challenge',
        'email/*', 'user/*', 'dashboard',
    ];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => fn () => app(GeneralSettings::class)->company_name,
            'auth' => [
                'user' => fn () => $user ? AuthUserResource::make($user)->resolve($request) : null,
                // Permission names drive what the UI shows. The server still authorizes every request.
                'permissions' => fn () => $this->permissionsFor($user),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function permissionsFor(?User $user): array
    {
        if ($user === null || ! $user->canAccessAdmin()) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
