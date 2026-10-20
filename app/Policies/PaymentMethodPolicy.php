<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentMethodsView);
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can(Permission::PaymentMethodsView);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->can(Permission::PaymentMethodsUpdate);
    }
}
