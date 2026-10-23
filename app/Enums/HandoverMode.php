<?php

namespace App\Enums;

/**
 * How the car changes hands at pickup or return (D37): at the office, or delivered to and
 * collected from the renter's address (D33).
 */
enum HandoverMode: string
{
    case Office = 'office';
    case Address = 'address';

    public function label(bool $return = false): string
    {
        return match ($this) {
            self::Office => 'At our office',
            self::Address => $return ? 'Collected from an address' : 'Delivered to an address',
        };
    }
}
