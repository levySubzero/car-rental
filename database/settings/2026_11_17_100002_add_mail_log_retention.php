<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The Email log holds message bodies, so it is kept for a short time.
        $this->migrator->add('retention.mail_log_retention_days', 30);
    }
};
