<?php

namespace App\Exceptions\Domain;

class RoleManagementException extends DomainException
{
    public static function systemRoleLocked(): self
    {
        return new self('The super admin role cannot be changed or deleted.');
    }

    public static function stillAssigned(int $users): self
    {
        return new self("This role is assigned to {$users} user(s). Move them to another role first.");
    }
}
