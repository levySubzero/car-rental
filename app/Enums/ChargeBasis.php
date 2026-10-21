<?php

namespace App\Enums;

/**
 * How a charge's amount is applied to a rental.
 */
enum ChargeBasis: string
{
    case PerDay = 'per_day';
    case PerBooking = 'per_booking';
    // Charged on actual use (excess kilometres): listed with its rate, never totalled.
    case PerKm = 'per_km';
    // Shown for information ("on request"); never totalled.
    case InfoOnly = 'info_only';

    public function label(): string
    {
        return match ($this) {
            self::PerDay => 'Per day',
            self::PerBooking => 'Per booking',
            self::PerKm => 'Per km',
            self::InfoOnly => 'Information only',
        };
    }

    /**
     * Whether the amount can be known when the quote is made.
     */
    public function isQuotable(): bool
    {
        return $this === self::PerDay || $this === self::PerBooking;
    }
}
