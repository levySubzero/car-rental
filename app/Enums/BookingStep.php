<?php

namespace App\Enums;

/**
 * The wizard steps saved on the server (PRD B-1). Payment steps join in M9.
 */
enum BookingStep: string
{
    case Trip = 'trip';
    case Driver = 'driver';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Trip => 'Trip and extras',
            self::Driver => 'Driver and documents',
            self::Review => 'Review and submit',
        };
    }

    public function next(): self
    {
        return match ($this) {
            self::Trip => self::Driver,
            self::Driver, self::Review => self::Review,
        };
    }

    public function position(): int
    {
        return match ($this) {
            self::Trip => 1,
            self::Driver => 2,
            self::Review => 3,
        };
    }
}
