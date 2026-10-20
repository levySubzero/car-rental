<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PagesView);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can(Permission::PagesView);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can(Permission::PagesUpdate);
    }
}
