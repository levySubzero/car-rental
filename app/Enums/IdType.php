<?php

namespace App\Enums;

/**
 * The identity document a driver books with (PRD Q6).
 */
enum IdType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'Kenyan national ID',
            self::Passport => 'Passport',
        };
    }
}
