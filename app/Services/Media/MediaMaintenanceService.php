<?php

namespace App\Services\Media;

use App\Exceptions\Domain\MediaException;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Storage housekeeping for media:cleanup and media:migrate-disk.
 */
class MediaMaintenanceService
{
    /**
     * Remove pending uploads past their expiry (never saved with a form), and the storage
     * of files deleted more than media.purge_after_days ago. Returns the counts.
     *
     * @return array{expired: int, purged: int}
     */
    public function cleanup(bool $dryRun = false): array
    {
        $expired = Media::query()->expired();
        $deleted = Media::onlyTrashed()->where('deleted_at', '<=', now()->subDays((int) config('media.purge_after_days')));

        if ($dryRun) {
            return ['expired' => $expired->count(), 'purged' => $deleted->count()];
        }

        return [
            'expired' => $this->purge($expired),
            'purged' => $this->purge($deleted),
        ];
    }

    /**
     * Move every file on $from to $to: original and conversions, verified by checksum,
     * one row at a time so an interrupted run can be resumed. Both disks must have the
     * same visibility.
     *
     * @param  callable(Media, string): void|null  $progress  called with each row and "moved", "failed" or "dry-run"
     * @return array{moved: int, failed: int}
     */
    public function migrateDisk(string $from, string $to, ?string $collection = null, bool $dryRun = false, bool $keepSource = false, ?callable $progress = null): array
    {
        $this->assertCompatibleDisks($from, $to);

        $counts = ['moved' => 0, 'failed' => 0];

        $query = Media::withTrashed()
            ->where('disk', $from)
            ->when($collection, fn ($query, string $collection) => $query->where('collection', $collection));

        $query->chunkById(100, function ($rows) use ($from, $to, $dryRun, $keepSource, $progress, &$counts): void {
            foreach ($rows as $media) {
                /** @var Media $media */
                if ($dryRun) {
                    $progress && $progress($media, 'dry-run');
                    $counts['moved']++;

                    continue;
                }

                try {
                    $this->moveFiles($media, $from, $to, $keepSource);
                    $counts['moved']++;
                    $progress && $progress($media, 'moved');
                } catch (Throwable $exception) {
                    $counts['failed']++;
                    Log::error('Media disk migration failed', ['media' => $media->uuid, 'error' => $exception->getMessage()]);
                    $progress && $progress($media, 'failed');
                }
            }
        });

        return $counts;
    }

    /**
     * @param  Builder<Media>  $query
     */
    private function purge($query): int
    {
        $count = 0;

        $query->chunkById(100, function ($rows) use (&$count): void {
            foreach ($rows as $media) {
                /** @var Media $media */
                Storage::disk($media->disk)->deleteDirectory($this->directoryOf($media));
                $media->forceDelete();
                $count++;
            }
        });

        return $count;
    }

    private function moveFiles(Media $media, string $from, string $to, bool $keepSource): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);
        $paths = [$media->path, ...array_values($media->conversions ?? [])];

        foreach ($paths as $path) {
            $stream = $source->readStream($path);

            if (! is_resource($stream)) {
                throw new RuntimeException("Missing file {$path}");
            }

            try {
                $target->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }
        }

        if (hash('sha256', (string) $target->get($media->path)) !== $media->checksum_sha256) {
            $target->deleteDirectory($this->directoryOf($media));

            throw new RuntimeException('Checksum mismatch after copy');
        }

        $media->forceFill(['disk' => $to])->saveQuietly();

        if (! $keepSource) {
            $source->deleteDirectory($this->directoryOf($media));
        }
    }

    private function assertCompatibleDisks(string $from, string $to): void
    {
        foreach ([$from, $to] as $disk) {
            if (! is_array(config("filesystems.disks.{$disk}"))) {
                throw new InvalidArgumentException("Unknown disk [{$disk}].");
            }
        }

        if ($from === $to) {
            throw new InvalidArgumentException('The source and target disks are the same.');
        }

        if (config("filesystems.disks.{$from}.visibility", 'private') !== config("filesystems.disks.{$to}.visibility", 'private')) {
            throw MediaException::crossVisibility();
        }
    }

    /**
     * Each file lives in its own {collection}/{Y}/{m}/{uuid}/ directory, so deleting it
     * removes the original and its conversions and nothing else.
     */
    private function directoryOf(Media $media): string
    {
        $directory = dirname($media->path);

        if (! str_ends_with($directory, '/'.$media->uuid)) {
            throw new RuntimeException("Refusing to delete [{$directory}]: not a media directory.");
        }

        return $directory;
    }
}
