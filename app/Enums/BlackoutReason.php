<?php

namespace App\Enums;

/**
 * Why a car is taken off the road for a period (car_blackouts).
 */
enum BlackoutReason: string
{
    case Maintenance = 'maintenance';
    case PrivateUse = 'private_use';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::PrivateUse => 'Private use',
        };
    }
}
