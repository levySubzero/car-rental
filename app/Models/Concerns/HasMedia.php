<?php

namespace App\Models\Concerns;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Files attached through the Media module (TECHNICAL_PLAN §6.5).
 * Attaching, ordering and deleting go through MediaService; this trait only reads.
 * A model's collections come from MediaCollectionRegistry::forOwner($model->getMorphClass())
 * (models may not use services).
 *
 * @mixin Model
 */
trait HasMedia
{
    public static function bootHasMedia(): void
    {
        // Soft-delete the owner's files with it; media:cleanup purges the storage later.
        static::deleting(function (Model $model): void {
            /** @var Model&self $model */
            $model->media()->delete();
        });
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('status', MediaStatus::Attached);
    }

    /**
     * Attached files of one collection, in their saved order.
     *
     * @return MorphMany<Media, $this>
     */
    public function mediaIn(string $collection): MorphMany
    {
        return $this->media()
            ->where('collection', $collection)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The cover of a collection, falling back to its first file.
     *
     * @return MorphOne<Media, $this>
     */
    public function primaryMedia(string $collection): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('status', MediaStatus::Attached)
            ->where('collection', $collection)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
