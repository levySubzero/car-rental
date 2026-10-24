<?php

namespace App\Enums;

/**
 * The fuel gauge at handover or return, in the quarters staff read off the dashboard.
 */
enum FuelLevel: string
{
    case Empty = 'empty';
    case Quarter = 'quarter';
    case Half = 'half';
    case ThreeQuarters = 'three_quarters';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'Empty',
            self::Quarter => '¼',
            self::Half => '½',
            self::ThreeQuarters => '¾',
            self::Full => 'Full',
        };
    }
}
