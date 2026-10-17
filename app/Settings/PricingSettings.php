<?php

namespace App\Settings;

use App\Enums\DayCountingStrategy;
use App\Enums\RoundingMode;
use Spatie\LaravelSettings\Settings;

/**
 * Quote engine inputs (TECHNICAL_PLAN §9, §10). Bookings snapshot these at submit time.
 */
class PricingSettings extends Settings
{
    public string $base_currency;

    public int $reservation_percent_bp;

    public RoundingMode $reservation_rounding;

    public DayCountingStrategy $day_counting;

    public string $daytime_window_start;

    public string $daytime_window_end;

    public int $rolling_grace_minutes;

    public int $hold_minutes;

    public int $draft_expiry_hours;

    public int $turnaround_minutes;

    public int $default_mileage_km;

    public ?int $unlimited_mileage_after_days;

    public static function group(): string
    {
        return 'pricing';
    }
}
