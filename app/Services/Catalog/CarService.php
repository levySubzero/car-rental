<?php

namespace App\Services\Catalog;

use App\Exceptions\Domain\CatalogException;
use App\Models\Car;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The fleet (PRD A-1). Field changes are logged by the model (LogsActivity, without the
 * car value); gallery changes by MediaService.
 *
 * @phpstan-type CarData array{
 *     brand_id: int, car_type_id: int, name: string, slug?: string|null, model_year?: int|null,
 *     engine_type?: string|null, engine_capacity_litres?: string|null, fuel_type: string, transmission: string,
 *     colour?: string|null, doors: int, seats: int, daily_rate_minor: int, car_value_minor?: int|null,
 *     mileage_limit_km?: int|null, damage_excess_minor?: int|null, damage_excess_currency?: string|null,
 *     description?: string|null, metadata?: array<string, string>|null,
 *     gallery?: list<string>, cover?: string|null
 * }
 */
class CarService
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * @param  array{search?: string|null, brand?: int|string|null, type?: int|string|null, status?: string|null, available?: string|null, featured?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, Car>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $sortable = ['name', 'daily_rate_minor', 'updated_at'];
        $sort = in_array($filters['sort'] ?? null, $sortable, true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Car::query()
            ->with(['brand', 'carType', 'cover'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['brand'] ?? null, fn (Builder $query, int|string $brand) => $query->where('brand_id', $brand))
            ->when($filters['type'] ?? null, fn (Builder $query, int|string $type) => $query->where('car_type_id', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_published', $status === 'published'))
            ->when($filters['available'] ?? null, fn (Builder $query, string $available) => $query->where('is_available', $available === 'yes'))
            ->when($filters['featured'] ?? null, fn (Builder $query, string $featured) => $query->where('is_featured', $featured === 'yes'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  CarData  $data
     */
    public function create(array $data, User $actor): Car
    {
        return DB::transaction(function () use ($data, $actor) {
            $car = Car::create($this->attributes($data));

            $this->syncGallery($car, $data, $actor);

            return $car;
        });
    }

    /**
     * Leaving `car_value_minor` out of $data keeps the stored value: staff without
     * cars.view-value never send it.
     *
     * @param  CarData  $data
     */
    public function update(Car $car, array $data, User $actor): Car
    {
        return DB::transaction(function () use ($car, $data, $actor) {
            $car->update($this->attributes($data));

            $this->syncGallery($car, $data, $actor);

            return $car;
        });
    }

    /**
     * A published car is listed on the public site. It needs a photo and a price first.
     */
    public function publish(Car $car): Car
    {
        if ($car->daily_rate_minor <= 0) {
            throw CatalogException::notPublishable('set a daily rate');
        }

        if (! $car->cover()->exists()) {
            throw CatalogException::notPublishable('add at least one photo');
        }

        $car->forceFill(['is_published' => true, 'published_at' => $car->published_at ?? now()])->save();

        return $car;
    }

    public function unpublish(Car $car): Car
    {
        $car->forceFill(['is_published' => false])->save();

        return $car;
    }

    /**
     * Unavailable cars stay listed but cannot be booked (off the road, sold, ...).
     */
    public function setAvailability(Car $car, bool $available): Car
    {
        $car->forceFill(['is_available' => $available])->save();

        return $car;
    }

    public function setFeatured(Car $car, bool $featured): Car
    {
        $car->forceFill(['is_featured' => $featured])->save();

        return $car;
    }

    /**
     * Soft delete: bookings will keep pointing at the car. Its photos are soft-deleted too.
     */
    public function delete(Car $car): void
    {
        DB::transaction(fn () => $car->delete());
    }

    /**
     * @param  CarData  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        // A blank slug means "keep it" on update and "generate from the name" on create.
        $attributes = Arr::except($data, filled($data['slug'] ?? null) ? ['gallery', 'cover'] : ['gallery', 'cover', 'slug']);

        if (array_key_exists('metadata', $attributes)) {
            $attributes['metadata'] = $attributes['metadata'] ?: null;
        }

        return $attributes;
    }

    /**
     * @param  CarData  $data
     */
    private function syncGallery(Car $car, array $data, User $actor): void
    {
        if (array_key_exists('gallery', $data)) {
            $this->media->sync($car, 'car.gallery', $data['gallery'], $data['cover'] ?? null, $actor);
        }
    }
}
