<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Empty: every collection uses its config/media.php limits.
        $this->migrator->add('media.collection_overrides', []);
    }
};
