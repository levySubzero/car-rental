<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentSubmission;
use App\Models\User;

/**
 * Staff checking renter payments (M9). Renters submit by booking token, never through here.
 * Accepting less than the reservation is checked by PaymentVerificationService.
 */
class PaymentSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsView);
    }

    public function view(User $user, PaymentSubmission $submission): bool
    {
        return $user->can(Permission::PaymentsView);
    }

    public function verify(User $user, PaymentSubmission $submission): bool
    {
        return $user->can(Permission::PaymentsView) && $user->can(Permission::PaymentsVerify);
    }

    public function reject(User $user, PaymentSubmission $submission): bool
    {
        return $user->can(Permission::PaymentsView) && $user->can(Permission::PaymentsReject);
    }
}
