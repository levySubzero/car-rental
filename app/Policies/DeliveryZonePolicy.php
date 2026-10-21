<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DeliveryZone;
use App\Models\User;

class DeliveryZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DeliveryZonesView);
    }

    public function view(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can(Permission::DeliveryZonesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DeliveryZonesCreate);
    }

    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can(Permission::DeliveryZonesUpdate);
    }

    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can(Permission::DeliveryZonesDelete);
    }
}
