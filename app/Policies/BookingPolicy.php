<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;

/**
 * Staff access to bookings. Renters reach their own booking by its token instead
 * (ResolveBookingToken), never through this policy. The desk's actions join in M11.
 */
class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BookingsView);
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsView);
    }

    /**
     * KYC documents (MediaPolicy defers here for private booking files).
     */
    public function viewMedia(User $user, Booking $booking): bool
    {
        return $user->can(Permission::BookingsView) && $user->can(Permission::BookingsViewDocuments);
    }
}
