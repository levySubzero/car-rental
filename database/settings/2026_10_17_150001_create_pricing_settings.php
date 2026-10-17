<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('pricing.base_currency', 'KES');
        $this->migrator->add('pricing.reservation_percent_bp', 2500);
        $this->migrator->add('pricing.reservation_rounding', 'ceil_major');
        $this->migrator->add('pricing.day_counting', 'calendar_inclusive');
        $this->migrator->add('pricing.daytime_window_start', '07:00');
        $this->migrator->add('pricing.daytime_window_end', '19:00');
        $this->migrator->add('pricing.rolling_grace_minutes', 60);
        $this->migrator->add('pricing.hold_minutes', 120);
        $this->migrator->add('pricing.draft_expiry_hours', 24);
        $this->migrator->add('pricing.turnaround_minutes', 0);
        $this->migrator->add('pricing.default_mileage_km', 300);
        $this->migrator->add('pricing.unlimited_mileage_after_days', null);
    }
};
