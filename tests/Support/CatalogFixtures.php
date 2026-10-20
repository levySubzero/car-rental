<?php

namespace Tests\Support;

use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Storage;

/**
 * Catalog tests use the real collections (car.gallery, brand.logo) on fake disks.
 */
final class CatalogFixtures
{
    public static function setUp(): void
    {
        Storage::fake('media-public-local', ['url' => 'http://localhost/media-files', 'throw' => true]);
        Storage::fake('media-private-local', ['throw' => true]);

        config()->set('media.disks', ['public' => 'media-public-local', 'private' => 'media-private-local']);
    }

    /**
     * A pending upload by $uploader, as the uploader component leaves it before the form is saved.
     */
    public static function photo(User $uploader, string $collection = 'car.gallery'): Media
    {
        $file = $collection === 'brand.logo' ? MediaFixtures::png() : MediaFixtures::jpeg();

        return app(MediaService::class)->upload($file, $collection, $uploader);
    }
}
