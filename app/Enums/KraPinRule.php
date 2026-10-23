<?php

namespace App\Enums;

/**
 * Whether the booking form asks for a KRA PIN, per id type and residency (PRD Q6).
 */
enum KraPinRule: string
{
    case Required = 'required';
    case Optional = 'optional';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Required',
            self::Optional => 'Optional',
            self::Hidden => 'Not asked',
        };
    }
}
