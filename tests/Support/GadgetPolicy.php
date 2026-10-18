<?php

namespace Tests\Support;

use App\Enums\Permission;
use App\Models\User;

/**
 * Stands in for an owner policy such as CarPolicy or BookingPolicy.
 */
class GadgetPolicy
{
    public function view(User $user, Gadget $gadget): bool
    {
        return $user->can(Permission::UsersView);
    }

    public function update(User $user, Gadget $gadget): bool
    {
        return $user->can(Permission::UsersUpdate);
    }
}
