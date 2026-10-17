<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Staff account management. Rules that also bind super admins (not your own
 * account, keep one super admin) live in UserService, because super admins
 * bypass policies.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersCreate);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::UsersDelete);
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $user->can(Permission::UsersAssignRoles);
    }

    public function grantPermissions(User $user, User $model): bool
    {
        return $user->can(Permission::UsersGrantPermissions);
    }
}
