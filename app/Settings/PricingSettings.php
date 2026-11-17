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

    public int $cash_hold_hours;

    public int $draft_expiry_hours;

    public int $turnaround_minutes;

    public int $default_mileage_km;

    public ?int $unlimited_mileage_after_days;

    // Master switch for the security deposit. Off: no deposit is quoted, the age bands still decide who may drive.
    public bool $security_deposit_enabled;

    // Transaction costs the client pays (2026-11): added to online payments, taken off refunds.
    // Basis points of the amount; the defaults are Paystack's Kenya rates.
    public int $mpesa_fee_bp;

    public int $card_fee_bp;

    public static function group(): string
    {
        return 'pricing';
    }
}
