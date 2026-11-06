<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMediaJob;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:regenerate {collections?* : The collections to process (default: those with link previews)}')]
#[Description('Queue the image conversions again, e.g. after adding a conversion')]
class MediaRegenerateCommand extends Command
{
    private const DEFAULT_COLLECTIONS = ['car.gallery', 'site.hero'];

    public function handle(): int
    {
        /** @var list<string> $argument */
        $argument = $this->argument('collections');
        $count = 0;

        Media::query()
            ->whereIn('collection', $argument === [] ? self::DEFAULT_COLLECTIONS : $argument)
            ->where('mime_type', 'like', 'image/%')
            ->lazyById()
            ->each(function (Media $media) use (&$count) {
                ProcessMediaJob::dispatch($media);
                $count++;
            });

        $this->components->info("Queued {$count} ".str('file')->plural($count).'.');

        return self::SUCCESS;
    }
}
