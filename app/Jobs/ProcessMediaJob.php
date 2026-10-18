<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes the WebP conversions for an uploaded image. The original was already
 * sanitized during the upload (D19), so this job only resizes.
 */
class ProcessMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    // The file may be deleted before the job runs.
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Media $media) {}

    public function handle(MediaService $media): void
    {
        $media->generateConversions($this->media);
    }

    public function failed(Throwable $exception): void
    {
        app(MediaService::class)->markProcessingFailed($this->media, $exception);
    }
}
