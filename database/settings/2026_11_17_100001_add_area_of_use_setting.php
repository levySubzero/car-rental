<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Printed on the voucher (client's field list, 2026-11).
        $this->migrator->add('general.area_of_use', 'Within Kenya');
    }
};
