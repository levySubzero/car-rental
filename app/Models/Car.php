<?php

namespace App\Models;

use App\Enums\AvailabilityBlockType;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Sortable;
use App\Settings\PricingSettings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One rentable unit. Money is in KES minor units (D6). `car_value_minor` is admin-only
 * (cars.view-value) and never reaches a public resource. `metadata` holds informal extras
 * such as 0–100 time, top speed or features (D20); it is not shown publicly by default.
 *
 * @property int $id
 * @property int $brand_id
 * @property int $car_type_id
 * @property string $name
 * @property string $slug
 * @property int|null $model_year
 * @property string|null $engine_type
 * @property string|null $engine_capacity_litres e.g. "2.5" (decimal cast)
 * @property FuelType $fuel_type
 * @property Transmission $transmission
 * @property string|null $colour
 * @property int $doors
 * @property int $seats
 * @property int $daily_rate_minor
 * @property int|null $car_value_minor
 * @property int|null $mileage_limit_km
 * @property int|null $damage_excess_minor
 * @property string|null $damage_excess_currency
 * @property bool $is_featured
 * @property bool $is_available
 * @property bool $is_published
 * @property CarbonImmutable|null $published_at
 * @property string|null $description
 * @property ArrayObject<string, string>|null $metadata
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Brand $brand
 * @property-read CarType $carType
 */
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory, HasMedia, HasSlug, LogsActivity, SoftDeletes, Sortable;

    protected $fillable = [
        'brand_id', 'car_type_id', 'name', 'slug', 'model_year', 'engine_type', 'engine_capacity_litres',
        'fuel_type', 'transmission', 'colour', 'doors', 'seats', 'daily_rate_minor', 'car_value_minor',
        'mileage_limit_km', 'damage_excess_minor', 'damage_excess_currency', 'description', 'metadata',
    ];

    // A safety net: resources list their fields explicitly, but toArray() must never leak it.
    protected $hidden = ['car_value_minor'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'model_year' => 'integer',
            'engine_capacity_litres' => 'decimal:1',
            'fuel_type' => FuelType::class,
            'transmission' => Transmission::class,
            'doors' => 'integer',
            'seats' => 'integer',
            'daily_rate_minor' => 'integer',
            'car_value_minor' => 'integer',
            'mileage_limit_km' => 'integer',
            'damage_excess_minor' => 'integer',
            'is_featured' => 'boolean',
            'is_available' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'metadata' => AsArrayObject::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * The car value is left out: anyone with activity.view could read it there.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cars')
            ->logFillable()
            ->logExcept(['car_value_minor'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<CarType, $this>
     */
    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
    }

    /**
     * Staff blocks only (maintenance, private use); holds and bookings are read by AvailabilityService.
     *
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function blackouts(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class)->where('type', AvailabilityBlockType::Blocked->value);
    }

    /**
     * Every block: holds, bookings and staff blocks (D35).
     *
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function gallery(): MorphMany
    {
        return $this->mediaIn('car.gallery');
    }

    /**
     * @return MorphOne<Media, $this>
     */
    public function cover(): MorphOne
    {
        return $this->primaryMedia('car.gallery');
    }

    /**
     * Published cars, whether or not they can be booked right now.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Published and not taken off the road by staff.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBookable(Builder $query): void
    {
        $query->where('is_published', true)->where('is_available', true);
    }

    public function dailyRate(): Money
    {
        return new Money($this->daily_rate_minor);
    }

    public function damageExcess(): ?Money
    {
        return $this->damage_excess_minor === null
            ? null
            : new Money($this->damage_excess_minor, $this->damage_excess_currency ?? 'KES');
    }

    /**
     * The car's own limit, or the site default (PRD F-4: shown on every car).
     */
    public function effectiveMileageLimitKm(): int
    {
        return $this->mileage_limit_km ?? app(PricingSettings::class)->default_mileage_km;
    }
}
