<?php

namespace App\Services\Pricing\Quote;

use App\Enums\DayCountingStrategy;
use App\Enums\RoundingMode;
use App\Services\Pricing\DayCounter\CalendarInclusive;
use App\Services\Pricing\DayCounter\DayCounter;
use App\Services\Pricing\DayCounter\DaytimeBlocks;
use App\Services\Pricing\DayCounter\Rolling24h;
use App\Settings\PricingSettings;

/**
 * The pricing settings a quote depends on, frozen. Bookings snapshot this with the quote
 * (D8), so a later settings change never alters an existing booking.
 */
final readonly class QuoteSettings
{
    public function __construct(
        public int $reservationPercentBp,
        public RoundingMode $reservationRounding,
        public DayCountingStrategy $dayCounting,
        public string $windowStart,
        public string $windowEnd,
        public int $graceMinutes,
        public int $defaultMileageKm,
        public ?int $unlimitedMileageAfterDays,
        public string $timezone,
    ) {}

    public static function fromSettings(PricingSettings $settings): self
    {
        return new self(
            reservationPercentBp: $settings->reservation_percent_bp,
            reservationRounding: $settings->reservation_rounding,
            dayCounting: $settings->day_counting,
            windowStart: $settings->daytime_window_start,
            windowEnd: $settings->daytime_window_end,
            graceMinutes: $settings->rolling_grace_minutes,
            defaultMileageKm: $settings->default_mileage_km,
            unlimitedMileageAfterDays: $settings->unlimited_mileage_after_days,
            timezone: (string) config('baines.timezone'),
        );
    }

    public function dayCounter(): DayCounter
    {
        return match ($this->dayCounting) {
            DayCountingStrategy::CalendarInclusive => new CalendarInclusive($this->timezone),
            DayCountingStrategy::Rolling24h => new Rolling24h($this->graceMinutes),
            DayCountingStrategy::DaytimeBlocks => new DaytimeBlocks($this->timezone, $this->windowStart, $this->windowEnd),
        };
    }

    /**
     * @return array{reservation_percent_bp: int, reservation_rounding: string, day_counting: string, daytime_window_start: string, daytime_window_end: string, rolling_grace_minutes: int, default_mileage_km: int, unlimited_mileage_after_days: int|null, timezone: string}
     */
    public function toArray(): array
    {
        return [
            'reservation_percent_bp' => $this->reservationPercentBp,
            'reservation_rounding' => $this->reservationRounding->value,
            'day_counting' => $this->dayCounting->value,
            'daytime_window_start' => $this->windowStart,
            'daytime_window_end' => $this->windowEnd,
            'rolling_grace_minutes' => $this->graceMinutes,
            'default_mileage_km' => $this->defaultMileageKm,
            'unlimited_mileage_after_days' => $this->unlimitedMileageAfterDays,
            'timezone' => $this->timezone,
        ];
    }

    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->toArray()));
    }
}
