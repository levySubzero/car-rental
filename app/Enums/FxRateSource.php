<?php

namespace App\Enums;

enum FxRateSource: string
{
    case Api = 'api';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'API',
            self::Manual => 'Manual',
        };
    }
}
