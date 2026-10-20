<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CarType;
use App\Models\User;

class CarTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CarTypesView);
    }

    public function view(User $user, CarType $carType): bool
    {
        return $user->can(Permission::CarTypesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CarTypesCreate);
    }

    public function update(User $user, CarType $carType): bool
    {
        return $user->can(Permission::CarTypesUpdate);
    }

    public function delete(User $user, CarType $carType): bool
    {
        return $user->can(Permission::CarTypesDelete);
    }
}
