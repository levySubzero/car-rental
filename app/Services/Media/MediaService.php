<?php

namespace App\Services\Media;

use App\Enums\MediaProcessing;
use App\Enums\MediaStatus;
use App\Exceptions\Domain\MediaException;
use App\Jobs\ProcessMediaJob;
use App\Models\Media;
use App\Support\Media\MediaCollection;
use finfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Uploads, attaches, orders and deletes files for every module (TECHNICAL_PLAN §6.5).
 * Forms never post files: they post the uuids returned by upload(), and the owning
 * module's service calls sync() inside its own transaction.
 */
class MediaService
{
    public function __construct(
        private readonly MediaCollectionRegistry $collections,
        private readonly ImageProcessor $images,
        private readonly MediaUrlGenerator $urls,
    ) {}

    /**
     * Store one file as a pending upload. The MIME type is sniffed from the bytes and the
     * stored extension comes from it, never from the client's file name. Images are
     * re-encoded before they touch the disk (D19); conversions are queued.
     */
    public function upload(UploadedFile $file, string $collection, Model $uploader): Media
    {
        $definition = $this->collections->get($collection);
        $sniffed = $this->sniffMime($file);
        $extension = $this->extensionFor($sniffed, $definition);

        if ((int) $file->getSize() > $definition->maxKb * 1024) {
            throw MediaException::tooLarge($this->formatKb($definition->maxKb));
        }

        $width = $height = null;
        $isImage = $this->images->handles($extension);

        if ($isImage) {
            $processed = $this->images->sanitize((string) $file->getRealPath(), $extension);
            [$contents, $extension, $mime] = [$processed->contents, $processed->extension, $processed->mimeType];
            [$width, $height] = [$processed->width, $processed->height];
        } else {
            $contents = (string) file_get_contents((string) $file->getRealPath());
            $mime = $sniffed;
        }

        $uuid = (string) Str::uuid();
        $fileName = Str::random(40).'.'.$extension;
        $path = $collection.'/'.now()->format('Y/m')."/{$uuid}/{$fileName}";
        $disk = $definition->visibility->disk();

        Storage::disk($disk)->put($path, $contents);

        try {
            $media = DB::transaction(function () use ($file, $definition, $uploader, $uuid, $fileName, $path, $disk, $contents, $extension, $mime, $width, $height, $isImage) {
                $media = Media::create([
                    'uuid' => $uuid,
                    'collection' => $definition->key,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'file_name' => $fileName,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => strlen($contents),
                    'checksum_sha256' => hash('sha256', $contents),
                    'width' => $width,
                    'height' => $height,
                    'visibility' => $definition->visibility,
                    'status' => MediaStatus::Pending,
                    'processing' => $isImage && $definition->conversions !== [] ? MediaProcessing::Queued : MediaProcessing::Skipped,
                    'metadata' => $isImage ? ['exif_stripped' => true] : [],
                    'uploaded_by_type' => $uploader->getMorphClass(),
                    'uploaded_by_id' => $uploader->getKey(),
                    'expires_at' => now()->addMinutes((int) config('media.pending_ttl')),
                ]);

                activity('media')->performedOn($media)->causedBy($uploader)
                    ->withProperties(['collection' => $definition->key, 'size_bytes' => $media->size_bytes])
                    ->event('uploaded')
                    ->log('uploaded');

                if ($media->processing === MediaProcessing::Queued) {
                    ProcessMediaJob::dispatch($media)->afterCommit();
                }

                return $media;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return $media;
    }

    /**
     * Store a file the system made, such as a voucher PDF, straight onto its owner. It
     * replaces the collection's current files when the collection holds one file.
     */
    public function storeGenerated(Model $owner, string $collection, string $contents, string $name, string $mime, ?Model $actor = null): Media
    {
        $definition = $this->collections->assertOwner($collection, $owner);
        $extension = $this->extensionFor($mime, $definition);

        if (strlen($contents) > $definition->maxKb * 1024) {
            throw MediaException::tooLarge($this->formatKb($definition->maxKb));
        }

        $uuid = (string) Str::uuid();
        $fileName = Str::random(40).'.'.$extension;
        $path = $collection.'/'.now()->format('Y/m')."/{$uuid}/{$fileName}";
        $disk = $definition->visibility->disk();

        Storage::disk($disk)->put($path, $contents);

        try {
            return DB::transaction(function () use ($owner, $definition, $actor, $uuid, $name, $fileName, $path, $disk, $contents, $extension, $mime) {
                $current = $this->filesOf($owner, $definition->key)->orderBy('sort_order')->get();
                $replaced = $definition->maxFiles === 1 ? $current : new Collection;

                foreach ($replaced as $media) {
                    $media->delete();
                }

                $media = Media::create([
                    'uuid' => $uuid,
                    'mediable_type' => $owner->getMorphClass(),
                    'mediable_id' => $owner->getKey(),
                    'collection' => $definition->key,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($name, 250, ''),
                    'file_name' => $fileName,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => strlen($contents),
                    'checksum_sha256' => hash('sha256', $contents),
                    'visibility' => $definition->visibility,
                    'status' => MediaStatus::Attached,
                    'processing' => MediaProcessing::Skipped,
                    'metadata' => [],
                    'sort_order' => $current->count() - $replaced->count(),
                    'is_primary' => $current->count() === $replaced->count(),
                    'uploaded_by_type' => $actor?->getMorphClass(),
                    'uploaded_by_id' => $actor?->getKey(),
                ]);

                activity('media')->performedOn($owner)->causedBy($actor)
                    ->withProperties([
                        'collection' => $definition->key,
                        'file' => $uuid,
                        'removed' => $replaced->pluck('uuid')->all(),
                    ])
                    ->event('generated')
                    ->log('file generated');

                return $media;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * Write every conversion of the file's collection (ProcessMediaJob). Safe to run again.
     */
    public function generateConversions(Media $media): void
    {
        if (! $this->collections->has($media->collection) || ! $media->isImage()) {
            $media->update(['processing' => MediaProcessing::Skipped]);

            return;
        }

        $storage = Storage::disk($media->disk);
        $original = (string) $storage->get($media->path);
        $directory = dirname($media->path).'/conversions';
        $conversions = [];

        foreach ($this->collections->get($media->collection)->conversions as $name) {
            /** @var array{width: int, format?: string, quality?: int} $spec */
            $spec = config("media.conversions.{$name}");
            $converted = $this->images->convert($original, $spec);
            $path = "{$directory}/{$name}.{$converted->extension}";

            $storage->put($path, $converted->contents);
            $conversions[$name] = $path;
        }

        $media->update(['conversions' => $conversions, 'processing' => MediaProcessing::Done]);
    }

    public function markProcessingFailed(Media $media, Throwable $exception): void
    {
        $media->update(['processing' => MediaProcessing::Failed]);

        Log::warning('Media conversions failed', ['media' => $media->uuid, 'error' => $exception->getMessage()]);
    }

    /**
     * Make $uuids, in order, the files of $owner's collection. Pending uploads are attached,
     * files left out are deleted, and the cover (or the first file) becomes primary.
     * A null owner syncs a standalone collection such as site.hero.
     *
     * Pending files must have been uploaded by $actor. Only $system code (seeders) may
     * attach anyone's pending upload (M12.1).
     *
     * @param  list<string>  $uuids
     * @return Collection<int, Media>
     */
    public function sync(?Model $owner, string $collection, array $uuids, ?string $cover = null, ?Model $actor = null, bool $system = false): Collection
    {
        $definition = $this->collections->assertOwner($collection, $owner);
        $uuids = array_values(array_unique($uuids));

        if (count($uuids) > $definition->maxFiles) {
            throw MediaException::tooManyFiles($definition->maxFiles);
        }

        $cover = in_array($cover, $uuids, true) ? $cover : ($uuids[0] ?? null);

        return DB::transaction(function () use ($owner, $definition, $uuids, $cover, $actor, $system) {
            $requested = Media::query()
                ->whereIn('uuid', $uuids)
                ->where('collection', $definition->key)
                ->lockForUpdate()
                ->get()
                ->keyBy('uuid');

            foreach ($uuids as $uuid) {
                $media = $requested->get($uuid);

                if ($media === null || ! $media->isAttachableTo($owner, $actor, $system)) {
                    throw MediaException::notAvailable();
                }
            }

            $removed = $this->filesOf($owner, $definition->key)
                ->whereNotIn('uuid', $uuids)
                ->get();

            foreach ($removed as $media) {
                $media->delete();
            }

            foreach ($uuids as $position => $uuid) {
                $requested[$uuid]->update([
                    'mediable_type' => $owner?->getMorphClass(),
                    'mediable_id' => $owner?->getKey(),
                    'status' => MediaStatus::Attached,
                    'expires_at' => null,
                    'sort_order' => $position,
                    'is_primary' => $uuid === $cover,
                ]);
            }

            if ($removed->isNotEmpty() || $requested->contains(fn (Media $media) => $media->wasChanged('status'))) {
                activity('media')->causedBy($actor)
                    ->when($owner !== null, fn ($log) => $log->performedOn($owner))
                    ->withProperties([
                        'collection' => $definition->key,
                        'files' => $uuids,
                        'removed' => $removed->pluck('uuid')->all(),
                    ])
                    ->event('synced')
                    ->log('files updated');
            }

            return $this->filesOf($owner, $definition->key)->orderBy('sort_order')->get();
        });
    }

    /**
     * Attach a standalone collection's files (no owner model), e.g. the hero image
     * referenced by HomeSettings::$hero_media_uuid.
     *
     * @param  list<string>  $uuids
     * @return Collection<int, Media>
     */
    public function claim(string $collection, array $uuids, ?Model $actor = null, bool $system = false): Collection
    {
        return $this->sync(null, $collection, $uuids, actor: $actor, system: $system);
    }

    /**
     * Add one file to the end of a collection.
     */
    public function attach(?Model $owner, string $collection, string $uuid, ?Model $actor = null): Media
    {
        /** @var list<string> $current */
        $current = $this->filesOf($owner, $collection)->orderBy('sort_order')->pluck('uuid')->values()->all();
        $primary = $this->filesOf($owner, $collection)->where('is_primary', true)->value('uuid');

        return $this->sync($owner, $collection, [...$current, $uuid], $primary, $actor)
            ->firstWhere('uuid', $uuid) ?? throw MediaException::notAvailable();
    }

    /**
     * Save a new order for a collection's files. $uuids must be exactly its current files.
     *
     * @param  list<string>  $uuids
     */
    public function reorder(?Model $owner, string $collection, array $uuids, ?Model $actor = null): void
    {
        $this->collections->assertOwner($collection, $owner);

        DB::transaction(function () use ($owner, $collection, $uuids, $actor) {
            $current = $this->filesOf($owner, $collection)->lockForUpdate()->get()->keyBy('uuid');

            if ($current->count() !== count($uuids) || array_diff($uuids, $current->keys()->all()) !== []) {
                throw MediaException::notAvailable();
            }

            foreach ($uuids as $position => $uuid) {
                $current[$uuid]->update(['sort_order' => $position]);
            }

            activity('media')->causedBy($actor)
                ->when($owner !== null, fn ($log) => $log->performedOn($owner))
                ->withProperties(['collection' => $collection, 'files' => $uuids])
                ->event('reordered')
                ->log('files reordered');
        });
    }

    /**
     * Delete one file: a pending upload, or an attached one (detach). The storage is purged
     * later by media:cleanup. A deleted cover passes to the next file.
     */
    public function delete(Media $media, ?Model $actor = null): void
    {
        DB::transaction(function () use ($media, $actor) {
            $media->delete();

            if ($media->is_primary && ! $media->isPending()) {
                $this->filesFor($media)->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }

            activity('media')->performedOn($media)->causedBy($actor)
                ->withProperties(['collection' => $media->collection])
                ->event('deleted')
                ->log('deleted');
        });
    }

    /**
     * Alt text and caption. Other metadata keys are system-owned.
     *
     * @param  array{alt?: string|null, caption?: string|null}  $data
     */
    public function updateMetadata(Media $media, array $data, ?Model $actor = null): Media
    {
        $metadata = $media->metadata?->getArrayCopy() ?? [];

        foreach (['alt', 'caption'] as $key) {
            if (array_key_exists($key, $data)) {
                $metadata[$key] = $data[$key] === null || $data[$key] === '' ? null : trim($data[$key]);
            }
        }

        $media->update(['metadata' => $metadata]);

        activity('media')->performedOn($media)->causedBy($actor)
            ->withProperties(['fields' => array_keys($data)])
            ->event('updated')
            ->log('details updated');

        return $media;
    }

    /**
     * Open a stored file for streaming. The caller has already authorized the request.
     *
     * @return array{stream: resource, mime: string, size: int, name: string}
     */
    public function open(Media $media, ?string $conversion = null): array
    {
        $path = $this->urls->pathFor($media, $conversion);
        $storage = Storage::disk($media->disk);
        $stream = $storage->readStream($path);

        if (! is_resource($stream)) {
            throw MediaException::notAvailable();
        }

        return [
            'stream' => $stream,
            'mime' => $path === $media->path ? $media->mime_type : (string) $storage->mimeType($path),
            'size' => (int) $storage->size($path),
            'name' => $this->urls->downloadName($media),
        ];
    }

    /**
     * Every view or download of a private file is logged (M2.12, TECHNICAL_PLAN §15).
     */
    public function logAccess(Media $media, string $event, ?Model $actor, ?string $ip, ?string $conversion = null): void
    {
        activity('media')->performedOn($media)->causedBy($actor)
            ->withProperties(array_filter([
                'collection' => $media->collection,
                'conversion' => $conversion,
                'ip' => $ip,
            ]))
            ->event($event)
            ->log($event);
    }

    /**
     * @return Builder<Media>
     */
    private function filesOf(?Model $owner, string $collection): Builder
    {
        return Media::query()
            ->where('collection', $collection)
            ->where('status', MediaStatus::Attached)
            ->when(
                $owner === null,
                fn (Builder $query) => $query->whereNull('mediable_type'),
                fn (Builder $query) => $query->where('mediable_type', $owner?->getMorphClass())->where('mediable_id', $owner?->getKey()),
            );
    }

    /**
     * The other attached files sharing $media's owner and collection.
     *
     * @return Builder<Media>
     */
    private function filesFor(Media $media): Builder
    {
        return Media::query()
            ->where('collection', $media->collection)
            ->where('status', MediaStatus::Attached)
            ->where('mediable_type', $media->mediable_type)
            ->where('mediable_id', $media->mediable_id);
    }

    /**
     * From the bytes, never the client's Content-Type or file name.
     */
    private function sniffMime(UploadedFile $file): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
    }

    private function extensionFor(string $mime, MediaCollection $definition): string
    {
        /** @var array<string, string> $map */
        $map = config('media.mime_types');
        $extension = $map[$mime] ?? null;

        if ($extension === null || ! $definition->allowsExtension($extension)) {
            throw MediaException::typeNotAllowed($definition->mimes);
        }

        return $extension;
    }

    private function formatKb(int $kb): string
    {
        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : "{$kb} KB";
    }
}
