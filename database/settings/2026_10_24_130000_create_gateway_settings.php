<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Set by mpesa:register-c2b (M11B.6).
        $this->migrator->add('gateways.c2b_registered_at', null);
        $this->migrator->add('gateways.c2b_registered_shortcode', null);
    }
};
