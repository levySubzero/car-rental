<?php

namespace App\Console\Commands;

use App\Services\Media\MediaMaintenanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:cleanup {--dry-run : Count what would be removed without removing it}')]
#[Description('Remove expired pending uploads and purge the storage of deleted media')]
class MediaCleanupCommand extends Command
{
    public function handle(MediaMaintenanceService $maintenance): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $maintenance->cleanup($dryRun);

        $verb = $dryRun ? 'would be removed' : 'removed';
        $this->components->twoColumnDetail('Expired pending uploads', "{$result['expired']} {$verb}");
        $this->components->twoColumnDetail('Deleted files purged', "{$result['purged']} {$verb}");

        return self::SUCCESS;
    }
}
