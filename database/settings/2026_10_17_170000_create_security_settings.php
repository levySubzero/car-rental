<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Off by default; staff can still enrol 2FA voluntarily from their security page.
        $this->migrator->add('security.require_two_factor', false);
    }
};
