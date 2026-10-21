<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FxSettings extends Settings
{
    // FxProvider value. The API key lives in env (FX_API_KEY), never in settings.
    public string $provider;

    public int $refresh_minutes;

    // Subtracted from the market KES rate for display prices (PRD §5: KES 3).
    public int $margin_minor;

    public int $stale_after_hours;

    // A fetched rate that moved more than this since the last API rate is held back for review.
    public int $max_jump_percent;

    public static function group(): string
    {
        return 'fx';
    }
}
