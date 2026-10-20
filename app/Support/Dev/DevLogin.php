<?php

namespace App\Support\Dev;

use App\Models\User;

/**
 * One-click sign-in as any active user from the login page. Local environment only.
 */
class DevLogin
{
    public static function enabled(): bool
    {
        return app()->environment('local') && (bool) config('baines.dev_login');
    }

    /**
     * Active users to pick from, staff first.
     *
     * @return list<array{id: int, name: string, email: string, roles: list<string>}>
     */
    public static function users(): array
    {
        /** @var list<array{id: int, name: string, email: string, roles: list<string>}> */
        return User::query()
            ->with('roles:id,name')
            ->where('is_active', true)
            ->orderByDesc('is_staff')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'is_staff'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => array_values($user->roles->pluck('name')->all()),
            ])
            ->values()
            ->all();
    }
}
