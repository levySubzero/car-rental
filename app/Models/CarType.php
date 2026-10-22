<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Sortable;
use Carbon\CarbonImmutable;
use Database\Factories\CarTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A body or use category (SUV, 4x4 Safari, Executive, ...). `sort_order` orders the home page rows.
 * `is_active` is whether the type is shown on the public site (default true); hiding a type
 * never hides its cars.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CarType extends Model
{
    /** @use HasFactory<CarTypeFactory> */
    use HasFactory, HasSlug, LogsActivity, Sortable;

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    // Matches the column default, so a new type is visible before it is saved too.
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('car-types')
            ->logOnly(['name', 'slug', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<Car, $this>
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * Types shown on the public site.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Only those with at least one published car, so no public list or filter shows an empty choice.
     *
     * @param  Builder<self>  $query
     */
    public function scopeHasPublishedCars(Builder $query): void
    {
        $query->whereHas('cars', /** @param Builder<Car> $cars */ fn (Builder $cars) => $cars->published());
    }
}
