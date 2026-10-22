<?php

namespace App\Services\Catalog;

use App\Exceptions\Domain\CatalogException;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Car categories (SUV, Executive, ...). The list is short, so it is never paginated.
 */
class CarTypeService
{
    /**
     * @return Collection<int, CarType>
     */
    public function all(): Collection
    {
        return CarType::query()->withCount('cars')->ordered()->get();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_values(CarType::query()->ordered()->get(['id', 'name'])
            ->map(fn (CarType $type) => ['value' => (string) $type->id, 'label' => $type->name])
            ->all());
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): CarType
    {
        return CarType::create($this->attributes($data));
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function update(CarType $type, array $data): CarType
    {
        $type->update($this->attributes($data));

        return $type;
    }

    /**
     * `is_active` is the type's visibility on the public site (header, footer, home rows,
     * filters). It does not hide the type's cars, and it is separate from a car's `is_featured`.
     */
    public function setVisibility(CarType $type, bool $visible): CarType
    {
        $type->update(['is_active' => $visible]);

        return $type;
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids, User $actor): void
    {
        CarType::reorder($ids);

        activity('car-types')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('car types reordered');
    }

    public function delete(CarType $type): void
    {
        $cars = Car::withTrashed()->where('car_type_id', $type->id)->count();

        if ($cars > 0) {
            throw CatalogException::carTypeInUse($cars);
        }

        DB::transaction(fn () => $type->delete());
    }

    /**
     * A blank slug means "keep it" on update and "generate from the name" on create.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return filled($data['slug'] ?? null) ? $data : Arr::except($data, ['slug']);
    }
}
