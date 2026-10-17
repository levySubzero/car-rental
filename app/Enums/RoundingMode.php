<?php

namespace App\Enums;

/**
 * How an amount in minor units is rounded after a percentage is applied.
 */
enum RoundingMode: string
{
    // Round up to the next whole major unit (KES 16,587.50 → 16,588.00).
    case CeilMajor = 'ceil_major';

    // Round half up to the nearest minor unit.
    case Round = 'round';

    // Round down to the minor unit.
    case Floor = 'floor';

    public function label(): string
    {
        return match ($this) {
            self::CeilMajor => 'Round up to the whole unit',
            self::Round => 'Round to the nearest cent',
            self::Floor => 'Round down to the cent',
        };
    }
}
