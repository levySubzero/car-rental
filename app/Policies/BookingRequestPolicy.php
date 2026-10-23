<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BookingRequest;
use App\Models\User;

/**
 * Staff handling renter requests (M10B). Renters send them from the portal, never through here.
 */
class BookingRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BookingRequestsView);
    }

    public function update(User $user, BookingRequest $request): bool
    {
        return $user->can(Permission::BookingRequestsView) && $user->can(Permission::BookingRequestsUpdate);
    }
}
