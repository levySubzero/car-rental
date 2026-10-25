<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Services\Media\MediaService;
use App\Settings\HomeSettings;
use Database\Seeders\Concerns\UploadsSeedMedia;
use Illuminate\Database\Seeder;

/**
 * Interim photos for the home hero and the service cards (data/media/stock.json), in every
 * environment. Insert-only: a hero or service that already has a photo, from staff or an
 * earlier run, is left alone, so the client's own photos replace these simply by uploading.
 */
class SiteMediaSeeder extends Seeder
{
    use UploadsSeedMedia;

    public function run(MediaService $media, HomeSettings $home): void
    {
        $uploader = $this->seedUploader();

        if ($uploader === null) {
            $this->command->warn('  Site photos skipped: no staff user to upload them as.');

            return;
        }

        /** @var array{hero: array{file: string}, services: array<string, array{file: string}>} $stock */
        $stock = json_decode((string) file_get_contents(database_path('seeders/data/media/stock.json')), true, flags: JSON_THROW_ON_ERROR);
        $added = 0;

        if ($home->hero_media_uuid === null) {
            $hero = $this->uploadSeedFile($stock['hero']['file'], 'site.hero', $uploader);
            $media->claim('site.hero', [$hero->uuid], system: true);
            $this->convertSeedMedia([$hero]);
            $home->hero_media_uuid = $hero->uuid;
            $home->save();
            $added++;
        }

        foreach (Service::query()->whereIn('slug', array_keys($stock['services']))->with('image')->get() as $service) {
            if ($service->image !== null) {
                continue;
            }

            $photo = $this->uploadSeedFile($stock['services'][$service->slug]['file'], 'service.image', $uploader);
            $media->sync($service, 'service.image', [$photo->uuid], system: true);
            $this->convertSeedMedia([$photo]);
            $added++;
        }

        $this->command->info("  Site photos: {$added} added");
    }
}
