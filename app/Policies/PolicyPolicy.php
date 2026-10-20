<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Policy;
use App\Models\User;

class PolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PoliciesView);
    }

    public function view(User $user, Policy $policy): bool
    {
        return $user->can(Permission::PoliciesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PoliciesCreate);
    }

    public function update(User $user, Policy $policy): bool
    {
        return $user->can(Permission::PoliciesUpdate);
    }

    public function delete(User $user, Policy $policy): bool
    {
        return $user->can(Permission::PoliciesDelete);
    }

    public function publish(User $user, Policy $policy): bool
    {
        return $user->can(Permission::PoliciesPublish);
    }
}
