<?php

namespace App\Models;

use App\Enums\MediaProcessing;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One stored file (TECHNICAL_PLAN §6.3). The uuid is the only identifier ever exposed.
 * Files are written and read through App\Services\Media\MediaService only.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $mediable_type
 * @property int|null $mediable_id
 * @property string $collection
 * @property string $disk
 * @property string $path
 * @property array<string, string>|null $conversions
 * @property string $original_name
 * @property string $file_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $checksum_sha256
 * @property int|null $width
 * @property int|null $height
 * @property MediaVisibility $visibility
 * @property MediaStatus $status
 * @property MediaProcessing $processing
 * @property bool $is_primary
 * @property int $sort_order
 * @property ArrayObject<string, mixed>|null $metadata
 * @property string|null $uploaded_by_type
 * @property int|null $uploaded_by_id
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversions' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'visibility' => MediaVisibility::class,
            'status' => MediaStatus::class,
            'processing' => MediaProcessing::class,
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => AsArrayObject::class,
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A staff user, or the model a renter's token resolved to (a booking from M8).
     *
     * @return MorphTo<Model, $this>
     */
    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', MediaStatus::Pending);
    }

    /**
     * Pending uploads past their expiry, for media:cleanup.
     *
     * @param  Builder<self>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', MediaStatus::Pending)->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInCollection(Builder $query, string $collection): void
    {
        $query->where('collection', $collection);
    }

    public function isPublic(): bool
    {
        return $this->visibility === MediaVisibility::Public;
    }

    public function isPending(): bool
    {
        return $this->status === MediaStatus::Pending;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Whether $model uploaded this file.
     */
    public function wasUploadedBy(?Model $model): bool
    {
        return $model !== null
            && $this->uploaded_by_type === $model->getMorphClass()
            && (string) $this->uploaded_by_id === (string) $model->getKey();
    }

    /**
     * Whether this file may be saved into $owner's collection (a null owner: a standalone
     * collection): a pending upload by $actor, or a file already attached there. Only
     * $system code (seeders) may take anyone's pending upload.
     */
    public function isAttachableTo(?Model $owner, ?Model $actor, bool $system = false): bool
    {
        if ($this->isPending()) {
            return $system || $this->wasUploadedBy($actor);
        }

        return $owner === null
            ? $this->mediable_type === null && $this->status === MediaStatus::Attached
            : $this->belongsToOwner($owner);
    }

    /**
     * Whether this file is attached to $model.
     */
    public function belongsToOwner(?Model $model): bool
    {
        return $model !== null
            && $this->mediable_type === $model->getMorphClass()
            && (string) $this->mediable_id === (string) $model->getKey();
    }
}
