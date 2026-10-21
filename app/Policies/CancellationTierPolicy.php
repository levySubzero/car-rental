<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CancellationTier;
use App\Models\User;

class CancellationTierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CancellationTiersView);
    }

    public function view(User $user, CancellationTier $cancellationTier): bool
    {
        return $user->can(Permission::CancellationTiersView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CancellationTiersCreate);
    }

    public function update(User $user, CancellationTier $cancellationTier): bool
    {
        return $user->can(Permission::CancellationTiersUpdate);
    }

    public function delete(User $user, CancellationTier $cancellationTier): bool
    {
        return $user->can(Permission::CancellationTiersDelete);
    }
}
