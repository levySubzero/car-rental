<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('retention.kyc_retention_months', 12);
        $this->migrator->add('retention.draft_retention_days', 7);
        $this->migrator->add('retention.activity_log_retention_months', 24);
    }
};
