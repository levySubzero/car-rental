<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('fx.provider', 'manual');
        $this->migrator->add('fx.refresh_minutes', 60);
        $this->migrator->add('fx.margin_minor', 300);
        $this->migrator->add('fx.stale_after_hours', 24);
    }
};
