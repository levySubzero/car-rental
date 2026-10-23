<?php

namespace App\Enums;

enum BookingRequestStatus: string
{
    case Open = 'open';
    case Handled = 'handled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Handled => 'Handled',
        };
    }
}
