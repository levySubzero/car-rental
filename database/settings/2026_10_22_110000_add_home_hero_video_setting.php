<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * An optional short video over the hero image (site.hero_video).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('home.hero_video_uuid', null);
    }

    public function down(): void
    {
        $this->migrator->delete('home.hero_video_uuid');
    }
};
