<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // How long a booking paying cash at the office keeps its car (D38).
        $this->migrator->add('pricing.cash_hold_hours', 24);
    }
};
