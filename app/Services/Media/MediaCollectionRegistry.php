<?php

namespace App\Services\Media;

use App\Exceptions\Domain\MediaException;
use App\Support\Media\MediaCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves media collections: config/media.php gives the ceilings, MediaSettings
 * overrides can only tighten them (TECHNICAL_PLAN §6.2). Bound in AppServiceProvider.
 */
class MediaCollectionRegistry
{
    /** @var array<string, MediaCollection> */
    private array $ceilings = [];

    /**
     * @param  array<string, array<string, mixed>>  $definitions  config('media.collections')
     * @param  array<string, array{max_files?: int|null, max_kb?: int|null, mimes?: list<string>|null}>  $overrides  MediaSettings::$collection_overrides
     * @param  list<string>  $unsupportedExtensions  extensions the image driver cannot decode (HEIC without Imagick)
     */
    public function __construct(
        array $definitions,
        private readonly array $overrides,
        array $unsupportedExtensions,
        int $kbCeiling,
    ) {
        foreach ($definitions as $key => $definition) {
            $collection = MediaCollection::fromConfig($key, $definition, $kbCeiling);

            foreach ($unsupportedExtensions as $extension) {
                $collection = $collection->withoutExtension($extension);
            }

            $this->ceilings[$key] = $collection;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->ceilings[$key]);
    }

    /**
     * The effective definition, with admin overrides applied.
     */
    public function get(string $key): MediaCollection
    {
        return $this->ceiling($key)->tightenedBy($this->overrides[$key] ?? []);
    }

    /**
     * The configured limits, before admin overrides. Overrides may never exceed these.
     */
    public function ceiling(string $key): MediaCollection
    {
        return $this->ceilings[$key] ?? throw MediaException::unknownCollection($key);
    }

    /**
     * @return array<string, MediaCollection>
     */
    public function all(): array
    {
        return array_map(fn (MediaCollection $collection) => $this->get($collection->key), $this->ceilings);
    }

    /**
     * @return array<string, MediaCollection>
     */
    public function ceilings(): array
    {
        return $this->ceilings;
    }

    /**
     * The collections owned by a morph alias (e.g. "car").
     *
     * @return array<string, MediaCollection>
     */
    public function forOwner(string $alias): array
    {
        return array_filter($this->all(), fn (MediaCollection $collection) => $collection->owner === $alias);
    }

    /**
     * Throw unless $owner may hold files of this collection. Standalone collections take no owner.
     */
    public function assertOwner(string $key, ?Model $owner): MediaCollection
    {
        $collection = $this->get($key);

        if ($owner?->getMorphClass() !== $collection->owner) {
            throw MediaException::wrongOwner();
        }

        return $collection;
    }
}
