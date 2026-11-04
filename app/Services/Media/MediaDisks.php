<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Resolved disks keep the keys they were built with, for the life of the process.
 */
class MediaDisks
{
    private const S3_DISKS = ['media-public-s3', 'media-private-s3'];

    /**
     * Rebuild the S3 disks on next use, after their keys changed (M12B.15).
     */
    public function forget(): void
    {
        Storage::forgetDisk(self::S3_DISKS);
    }
}
