<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FxSettings extends Settings
{
    // manual | open_exchange_rates | exchangerate_api
    public string $provider;

    public int $refresh_minutes;

    // Subtracted from the market KES rate for display prices (PRD §5: KES 3).
    public int $margin_minor;

    public int $stale_after_hours;

    public static function group(): string
    {
        return 'fx';
    }
}
