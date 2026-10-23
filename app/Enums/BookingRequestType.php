<?php

namespace App\Enums;

/**
 * What a renter asks for from the portal (M10B). Staff act on it by hand.
 */
enum BookingRequestType: string
{
    case Cancel = 'cancel';
    case Change = 'change';

    public function label(): string
    {
        return match ($this) {
            self::Cancel => 'Cancellation',
            self::Change => 'Change',
        };
    }
}
