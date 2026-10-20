<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ContactMessagesView);
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can(Permission::ContactMessagesView);
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can(Permission::ContactMessagesUpdate);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can(Permission::ContactMessagesDelete);
    }
}
