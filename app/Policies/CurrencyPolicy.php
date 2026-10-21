<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CurrenciesView);
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->can(Permission::CurrenciesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CurrenciesCreate);
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->can(Permission::CurrenciesUpdate);
    }
}
