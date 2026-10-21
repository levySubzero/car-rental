<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DepositBand;
use App\Models\User;

class DepositBandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DepositBandsView);
    }

    public function view(User $user, DepositBand $depositBand): bool
    {
        return $user->can(Permission::DepositBandsView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DepositBandsCreate);
    }

    public function update(User $user, DepositBand $depositBand): bool
    {
        return $user->can(Permission::DepositBandsUpdate);
    }

    public function delete(User $user, DepositBand $depositBand): bool
    {
        return $user->can(Permission::DepositBandsDelete);
    }
}
