<?php

namespace Database\Seeders\Concerns;

use App\Models\Car;
use App\Services\Catalog\CarService;
use App\Services\Media\MediaService;

/**
 * Gives a seeded car its photos from database/seeders/data/media and publishes it.
 */
trait AttachesCarPhotos
{
    use UploadsSeedMedia;

    /**
     * A car that already has photos is left alone, so staff changes survive a re-seed.
     * The first file is the cover.
     *
     * @param  list<string>  $files  paths relative to database/seeders/data/media
     */
    protected function attachPhotos(Car $car, array $files): void
    {
        if ($car->gallery()->exists()) {
            return;
        }

        $uploader = $this->seedUploader();

        if ($uploader === null) {
            $this->command->warn("  Photos for {$car->name} skipped: no staff user to upload them as.");

            return;
        }

        $uploads = array_map(fn (string $file) => $this->uploadSeedFile($file, 'car.gallery', $uploader), $files);
        $uuids = array_map(fn ($upload) => $upload->uuid, $uploads);

        app(MediaService::class)->sync($car, 'car.gallery', $uuids, $uuids[0]);
        $this->convertSeedMedia($uploads);

        app(CarService::class)->publish($car);
    }
}
