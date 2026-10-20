<?php

namespace App\Models;

use App\Exceptions\Domain\CmsException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One numbered text of a policy. `body` is Markdown (D23). Once published it never changes.
 * A null `created_by` means the seed pack wrote it.
 *
 * @property int $id
 * @property int $policy_id
 * @property int $version
 * @property string $body
 * @property CarbonImmutable|null $published_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Policy $policy
 */
class PolicyVersion extends Model
{
    protected $fillable = ['version', 'body', 'published_at', 'created_by'];

    protected static function booted(): void
    {
        // What renters accepted must stay word for word (D24). Publishing a draft is the only
        // change a version can take.
        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') !== null) {
                throw CmsException::versionPublished();
            }
        });

        static::deleting(function (self $version): void {
            if ($version->isPublished()) {
                throw CmsException::versionPublished();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @return BelongsTo<Policy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<PolicyBundle, $this>
     */
    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(PolicyBundle::class, 'policy_bundle_version');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }
}
