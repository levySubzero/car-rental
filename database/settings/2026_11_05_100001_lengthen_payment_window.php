<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The window now opens when staff accept and reaches the renter by email: two hours is
        // too short. A value someone already changed is left alone.
        $this->migrator->update('pricing.hold_minutes', fn (int $minutes) => $minutes === 120 ? 1440 : $minutes);
    }
};
