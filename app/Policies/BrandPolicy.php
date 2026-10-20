<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BrandsView);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->can(Permission::BrandsView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BrandsCreate);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can(Permission::BrandsUpdate);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can(Permission::BrandsDelete);
    }
}
