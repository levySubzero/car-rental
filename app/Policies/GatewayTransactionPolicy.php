<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GatewayTransaction;
use App\Models\User;

/**
 * Online receipts staff resolve by hand (M11B.9): seen with the payments queue, attached or
 * closed by those who verify payments.
 */
class GatewayTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsView);
    }

    public function resolve(User $user, GatewayTransaction $transaction): bool
    {
        return $user->can(Permission::PaymentsView) && $user->can(Permission::PaymentsVerify);
    }
}
