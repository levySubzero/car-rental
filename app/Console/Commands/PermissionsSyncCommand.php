<?php

namespace App\Console\Commands;

use App\Services\Iam\PermissionSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync {--prune : Delete permissions that are no longer in the Permission enum}')]
#[Description('Write the Permission enum to the database and keep the super admin role in place')]
class PermissionsSyncCommand extends Command
{
    public function handle(PermissionSyncService $sync): int
    {
        $result = $sync->sync((bool) $this->option('prune'));

        $this->components->info(sprintf('%d permission(s) created.', count($result['created'])));

        foreach ($result['created'] as $name) {
            $this->components->twoColumnDetail($name, '<fg=green>created</>');
        }

        if ($result['orphaned'] !== []) {
            $this->components->warn(sprintf(
                '%d orphaned permission(s) %s: %s',
                count($result['orphaned']),
                $result['pruned'] ? 'deleted' : 'found (run with --prune to delete)',
                implode(', ', $result['orphaned']),
            ));
        }

        return self::SUCCESS;
    }
}
