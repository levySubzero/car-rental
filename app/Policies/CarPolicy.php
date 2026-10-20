<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CarsView);
    }

    public function view(User $user, Car $car): bool
    {
        return $user->can(Permission::CarsView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CarsCreate);
    }

    public function update(User $user, Car $car): bool
    {
        return $user->can(Permission::CarsUpdate);
    }

    public function delete(User $user, Car $car): bool
    {
        return $user->can(Permission::CarsDelete);
    }

    public function publish(User $user, Car $car): bool
    {
        return $user->can(Permission::CarsPublish);
    }

    /**
     * The car's value is admin-only (PRD A-1). Check with the class on create forms.
     */
    public function viewValue(User $user, ?Car $car = null): bool
    {
        return $user->can(Permission::CarsViewValue);
    }

    /**
     * Maintenance blackouts now; the availability calendar in M11.
     */
    public function manageAvailability(User $user, Car $car): bool
    {
        return $user->can(Permission::AvailabilityManage);
    }
}
