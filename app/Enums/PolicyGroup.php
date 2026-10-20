<?php

namespace App\Enums;

/**
 * Sections of the rental policy page (PRD §11), in display order.
 */
enum PolicyGroup: string
{
    case Booking = 'booking';
    case Rental = 'rental';
    case Pickup = 'pickup';
    case Deposit = 'deposit';
    case Cover = 'cover';
    case Charges = 'charges';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking and payment',
            self::Rental => 'During your rental',
            self::Pickup => 'At pick-up',
            self::Deposit => 'Deposit and excess',
            self::Cover => 'Insurance and cover',
            self::Charges => 'Charges and fees',
            self::General => 'General information',
        };
    }
}
