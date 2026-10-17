<?php

namespace App\Http\Requests\Admin\User\Concerns;

use App\Enums\Permission;
use Illuminate\Validation\Rule;

trait AccessRules
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function accessRules(): array
    {
        return [
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ];
    }

    /**
     * Changing roles or direct permissions needs its own permission on top of create/update.
     */
    protected function canChangeSubmittedAccess(mixed $target): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->has('roles') && ! $user->can('assignRoles', $target)) {
            return false;
        }

        return ! $this->has('permissions') || $user->can('grantPermissions', $target);
    }
}
