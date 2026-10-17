<?php

namespace App\Services\Iam;

use App\Exceptions\Domain\UserManagementException;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserService
{
    /**
     * @param  array{search?: string|null, role?: string|null, status?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $sortable = ['name', 'email', 'last_login_at', 'created_at'];
        $sort = in_array($filters['sort'] ?? null, $sortable, true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->where('is_staff', true)
            ->with('roles')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a staff account and email an invitation to set a password.
     *
     * @param  array{name: string, email: string, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function invite(array $data, User $actor): User
    {
        $user = DB::transaction(function () use ($data, $actor) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Str::password(40),
                'is_staff' => true,
                'is_active' => true,
            ]);

            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);

            activity('users')->performedOn($user)->causedBy($actor)
                ->withProperties(['roles' => $data['roles'] ?? [], 'permissions' => $data['permissions'] ?? []])
                ->event('invited')
                ->log('invited');

            return $user;
        });

        $this->sendInvitation($user, $actor);

        return $user;
    }

    public function resendInvitation(User $user, User $actor): void
    {
        $this->sendInvitation($user, $actor);

        activity('users')->performedOn($user)->causedBy($actor)->event('invitation-resent')->log('invitation resent');
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill(['name' => $data['name'], 'email' => Str::lower($data['email'])]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    /**
     * Replace a user's roles and/or direct permissions. Pass null to leave either untouched.
     *
     * @param  list<string>|null  $roles
     * @param  list<string>|null  $permissions
     */
    public function syncAccess(User $user, ?array $roles, ?array $permissions, User $actor): User
    {
        return DB::transaction(function () use ($user, $roles, $permissions, $actor) {
            $before = $this->accessOf($user);

            if ($roles !== null) {
                $superAdmin = config('baines.super_admin_role');

                if ($user->is($actor) && $user->hasRole($superAdmin) && ! in_array($superAdmin, $roles, true)) {
                    throw UserManagementException::cannotChangeOwnAccess();
                }

                if (! in_array($superAdmin, $roles, true)) {
                    $this->ensureAnotherSuperAdmin($user);
                }

                $user->syncRoles($roles);
            }

            if ($permissions !== null) {
                $user->syncPermissions($permissions);
            }

            $user->unsetRelation('roles')->unsetRelation('permissions');
            $after = $this->accessOf($user);

            if ($before !== $after) {
                activity('users')->performedOn($user)->causedBy($actor)
                    ->withChanges(['attributes' => $after, 'old' => $before])
                    ->event('access-changed')
                    ->log('access changed');
            }

            return $user;
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if ($user->is($actor)) {
            throw UserManagementException::cannotChangeOwnAccess();
        }

        if (! $active) {
            $this->ensureAnotherSuperAdmin($user);
        }

        $user->update(['is_active' => $active]);

        return $user;
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw UserManagementException::cannotChangeOwnAccess();
        }

        $this->ensureAnotherSuperAdmin($user);

        DB::transaction(function () use ($user, $actor) {
            activity('users')->performedOn($user)->causedBy($actor)
                ->withProperties(Arr::only($user->toArray(), ['name', 'email']))
                ->event('deleted')
                ->log('deleted');

            $user->delete();
        });
    }

    /**
     * Throw if this user may not delete their own account (the last active super admin).
     */
    public function ensureCanDeleteOwnAccount(User $user): void
    {
        $this->ensureAnotherSuperAdmin($user);
    }

    /**
     * A staff member deleting their own account. Sign them out before calling this:
     * logging out afterwards would save, and so re-create, the deleted user.
     */
    public function deleteOwnAccount(User $user): void
    {
        $this->ensureCanDeleteOwnAccount($user);

        DB::transaction(function () use ($user) {
            activity('users')->performedOn($user)->causedBy($user)
                ->withProperties(Arr::only($user->toArray(), ['name', 'email']))
                ->event('deleted')
                ->log('deleted their own account');

            $user->delete();
        });
    }

    /**
     * Throw if removing this user's super admin access would leave no active super admin.
     */
    private function ensureAnotherSuperAdmin(User $user): void
    {
        $role = config('baines.super_admin_role');

        if (! $user->hasRole($role)) {
            return;
        }

        $others = User::role($role)->where('is_active', true)->whereKeyNot($user->getKey())->exists();

        if (! $others) {
            throw UserManagementException::lastSuperAdmin();
        }
    }

    /**
     * @return array{roles: array<int, string>, permissions: array<int, string>}
     */
    private function accessOf(User $user): array
    {
        return [
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'permissions' => $user->getDirectPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }

    private function sendInvitation(User $user, User $actor): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(new StaffInvitation($token, $actor->name));
    }
}
