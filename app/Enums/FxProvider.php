<?php

namespace App\Enums;

/**
 * Where exchange rates come from (FxSettings::provider). Manual means staff enter every rate.
 */
enum FxProvider: string
{
    case Manual = 'manual';
    case OpenExchangeRates = 'open_exchange_rates';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual (staff enter the rates)',
            self::OpenExchangeRates => 'Open Exchange Rates',
        };
    }
}
