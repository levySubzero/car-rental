<?php

namespace App\Exceptions\Domain;

class UserManagementException extends DomainException
{
    public static function cannotChangeOwnAccess(): self
    {
        return new self('You cannot deactivate, delete or remove roles from your own account.');
    }

    public static function lastSuperAdmin(): self
    {
        return new self('At least one active super admin must remain. Make someone else a super admin first.');
    }
}
