<?php

namespace App\Console\Commands;

use App\Exceptions\Domain\MediaException;
use App\Models\Media;
use App\Services\Media\MediaMaintenanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('media:migrate-disk
    {from : Disk the files are on, e.g. media-public-local}
    {to : Disk to move them to, e.g. media-public-s3}
    {--collection= : Only move one collection}
    {--dry-run : List what would move without copying}
    {--keep-source : Leave the files on the source disk}')]
#[Description('Move media files between disks of the same visibility, verifying each copy')]
class MediaMigrateDiskCommand extends Command
{
    public function handle(MediaMaintenanceService $maintenance): int
    {
        $collection = $this->option('collection');
        $from = $this->argument('from');
        $to = $this->argument('to');

        if (! is_string($from) || ! is_string($to)) {
            return self::INVALID;
        }

        try {
            $result = $maintenance->migrateDisk(
                $from,
                $to,
                is_string($collection) ? $collection : null,
                (bool) $this->option('dry-run'),
                (bool) $this->option('keep-source'),
                fn (Media $media, string $outcome) => $this->components->twoColumnDetail(
                    "{$media->collection} {$media->uuid}",
                    match ($outcome) {
                        'moved' => '<fg=green>moved</>',
                        'failed' => '<fg=red>failed (see log)</>',
                        default => '<fg=yellow>would move</>',
                    },
                ),
            );
        } catch (InvalidArgumentException|MediaException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%d file(s) %s, %d failed.', $result['moved'], $this->option('dry-run') ? 'would move' : 'moved', $result['failed']));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
