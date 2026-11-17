<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Deposits stay on, as they were; staff can now switch them off from Pricing settings.
        $this->migrator->add('pricing.security_deposit_enabled', true);
    }
};
