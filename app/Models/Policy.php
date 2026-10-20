<?php

namespace App\Models;

use App\Enums\PolicyGroup;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Sortable;
use Carbon\CarbonImmutable;
use Database\Factories\PolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One section of the rental policy (PRD §11). The text lives in versions (D24): the latest
 * published version is what the site shows, and at most one unpublished draft waits for review.
 * Deleted policies are soft-deleted because bundles renters accepted still point at their versions.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property PolicyGroup $group
 * @property string|null $summary
 * @property bool $show_on_home
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read PolicyVersion|null $published
 * @property-read PolicyVersion|null $draft
 */
class Policy extends Model
{
    /** @use HasFactory<PolicyFactory> */
    use HasFactory, HasSlug, LogsActivity, SoftDeletes, Sortable;

    protected $fillable = ['slug', 'title', 'group', 'summary', 'show_on_home'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => PolicyGroup::class,
            'show_on_home' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function slugSource(): string
    {
        return 'title';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('policies')
            ->logOnly(['title', 'slug', 'group', 'summary', 'show_on_home'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<PolicyVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class)->orderByDesc('version');
    }

    /**
     * The version the site shows: the latest published one.
     *
     * @return HasOne<PolicyVersion, $this>
     */
    public function published(): HasOne
    {
        return $this->hasOne(PolicyVersion::class)->ofMany(
            ['version' => 'max'],
            fn ($query) => $query->whereNotNull('published_at'),
        );
    }

    /**
     * @return HasOne<PolicyVersion, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(PolicyVersion::class)->whereNull('published_at');
    }
}
