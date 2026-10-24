<?php

namespace App\Enums;

/**
 * Which end of the hire a handover record covers (M11.6, M11.7).
 */
enum HandoverType: string
{
    case Handover = 'handover';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Handover => 'Handover',
            self::Return => 'Return',
        };
    }
}
