<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\Media;
use App\Models\User;
use App\Services\Booking\BookingTokenResolver;

/**
 * Staff access to bookings. Renters reach their own booking by its token instead
 * (ResolveBookingToken), never through this policy. Each desk action (M11) also needs
 * bookings.view.
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

    public function resendVoucher(User $user, Booking $booking): bool
    {
        return $this->desk($user, Permission::BookingsResendVoucher);
    }

    public function recordHandover(User $user, Booking $booking): bool
    {
        return $this->desk($user, Permission::BookingsRecordHandover);
    }

    public function recordReturn(User $user, Booking $booking): bool
    {
        return $this->desk($user, Permission::BookingsRecordReturn);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->desk($user, Permission::BookingsCancel);
    }

    public function updateDelivery(User $user, Booking $booking): bool
    {
        return $this->desk($user, Permission::BookingsUpdateDelivery);
    }

    public function legalHold(User $user, Booking $booking): bool
    {
        return $this->desk($user, Permission::BookingsLegalHold);
    }

    /**
     * Private booking files (MediaPolicy defers here): KYC documents need
     * bookings.view-documents; payment proofs are also open to whoever checks payments, and
     * the voucher to anyone who sees the booking or its payments.
     */
    public function viewMedia(User $user, Booking $booking, ?Media $media = null): bool
    {
        if ($media?->collection === BookingTokenResolver::PAYMENT_PROOF && $user->can(Permission::PaymentsView)) {
            return true;
        }

        if ($media?->collection === 'booking.voucher' && ($user->can(Permission::BookingsView) || $user->can(Permission::PaymentsView))) {
            return true;
        }

        return $user->can(Permission::BookingsView) && $user->can(Permission::BookingsViewDocuments);
    }

    private function desk(User $user, Permission $permission): bool
    {
        return $user->can(Permission::BookingsView) && $user->can($permission);
    }
}
