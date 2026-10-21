<?php

namespace App\Enums;

/**
 * When a charge is paid. Counter charges are settled at the desk and left out of the total.
 */
enum ChargePayableAt: string
{
    case Reservation = 'reservation';
    case Handover = 'handover';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Reservation => 'In the booking total',
            self::Handover => 'At handover (in the total)',
            self::Counter => 'At the counter (not in the total)',
        };
    }
}
