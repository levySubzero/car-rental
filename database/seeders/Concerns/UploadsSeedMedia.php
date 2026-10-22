<?php

namespace Database\Seeders\Concerns;

use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;

/**
 * Uploads a file from database/seeders/data/media the way an admin upload would (re-encoded,
 * EXIF stripped, size checked). The caller attaches it, then writes its conversions.
 */
trait UploadsSeedMedia
{
    /**
     * Null when there is no staff user to upload as; the super admin is created first.
     */
    protected function seedUploader(): ?User
    {
        return User::query()->where('is_staff', true)->orderBy('id')->first();
    }

    protected function uploadSeedFile(string $file, string $collection, User $uploader): Media
    {
        $path = database_path("seeders/data/media/{$file}");

        return app(MediaService::class)
            ->upload(new UploadedFile($path, basename($path), 'image/jpeg', null, true), $collection, $uploader);
    }

    /**
     * Conversions are written straight away so the site shows them without a queue worker.
     *
     * @param  iterable<Media>  $media
     */
    protected function convertSeedMedia(iterable $media): void
    {
        foreach ($media as $item) {
            app(MediaService::class)->generateConversions($item->refresh());
        }
    }
}
