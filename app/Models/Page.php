<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Carbon\CarbonImmutable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The editable content of a preset page (D22). Which pages exist and which fields each one
 * has is defined in code by App\Support\Content\PageRegistry; this row only holds the values.
 * Image fields hold media uuids from the `page.image` collection.
 *
 * @property int $id
 * @property string $slug
 * @property ArrayObject<string, mixed> $content
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasMedia, LogsActivity;

    protected $fillable = ['slug', 'content', 'meta_title', 'meta_description', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => AsArrayObject::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pages')
            ->logOnly(['content', 'meta_title', 'meta_description'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
