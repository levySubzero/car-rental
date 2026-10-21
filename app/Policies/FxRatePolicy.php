<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FxRate;
use App\Models\User;

class FxRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FxRatesView);
    }

    /**
     * Setting or ending a manual rate, and refreshing on demand.
     */
    public function override(User $user): bool
    {
        return $user->can(Permission::FxRatesOverride);
    }

    public function update(User $user, FxRate $rate): bool
    {
        return $user->can(Permission::FxRatesOverride);
    }
}
