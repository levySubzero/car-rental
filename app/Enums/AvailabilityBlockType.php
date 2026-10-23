<?php

namespace App\Enums;

/**
 * Why a car is unavailable for a period (car_availability_blocks, D35).
 */
enum AvailabilityBlockType: string
{
    // A submitted booking awaiting payment; lapses at expires_at.
    case Hold = 'hold';
    // A reserved booking.
    case Booked = 'booked';
    // Staff took the car off the road (maintenance, private use).
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Hold => 'Held',
            self::Booked => 'Booked',
            self::Blocked => 'Blocked',
        };
    }
}
