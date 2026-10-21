<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ChargesView);
    }

    public function view(User $user, Charge $charge): bool
    {
        return $user->can(Permission::ChargesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ChargesCreate);
    }

    public function update(User $user, Charge $charge): bool
    {
        return $user->can(Permission::ChargesUpdate);
    }

    public function delete(User $user, Charge $charge): bool
    {
        return $user->can(Permission::ChargesDelete);
    }
}
