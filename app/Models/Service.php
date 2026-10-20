<?php

namespace App\Models;

use App\Enums\ServiceCtaType;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Sortable;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Something Baines offers besides a plain rental (PRD A-2): chauffeur, transfers, safaris.
 * `body` is Markdown (D23); the image is the `service.image` media collection.
 * `cta_value` is the URL for a link button, or an optional WhatsApp message.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string|null $body
 * @property ServiceCtaType $cta_type
 * @property string|null $cta_value
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasMedia, HasSlug, LogsActivity, Sortable;

    protected $fillable = ['title', 'slug', 'summary', 'body', 'cta_type', 'cta_value', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cta_type' => ServiceCtaType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function slugSource(): string
    {
        return 'title';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('services')
            ->logOnly(['title', 'slug', 'summary', 'body', 'cta_type', 'cta_value', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return MorphOne<Media, $this>
     */
    public function image(): MorphOne
    {
        return $this->primaryMedia('service.image');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
