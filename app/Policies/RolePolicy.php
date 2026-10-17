<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * The locked super admin role is protected in RoleService, because super admins bypass policies.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RolesCreate);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesUpdate);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesDelete);
    }
}
