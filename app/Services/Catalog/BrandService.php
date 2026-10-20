<?php

namespace App\Services\Catalog;

use App\Exceptions\Domain\CatalogException;
use App\Models\Brand;
use App\Models\Car;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Car makes and the home page marquee (PRD A-2, H-4).
 */
class BrandService
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * @param  array{search?: string|null, status?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $sortable = ['name', 'sort_order', 'cars_count', 'updated_at'];
        $sort = in_array($filters['sort'] ?? null, $sortable, true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Brand::query()
            ->with('logo')
            ->withCount('cars')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Every brand in marquee order, for the ordering screen.
     *
     * @return Collection<int, Brand>
     */
    public function ordered(): Collection
    {
        return Brand::query()->with('logo')->ordered()->get();
    }

    /**
     * Id and name of every brand, for select inputs.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_values(Brand::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Brand $brand) => ['value' => (string) $brand->id, 'label' => $brand->name])
            ->all());
    }

    /**
     * @param  array{name: string, slug?: string|null, show_in_marquee?: bool, is_active?: bool, logo?: string|null}  $data
     */
    public function create(array $data, User $actor): Brand
    {
        return DB::transaction(function () use ($data, $actor) {
            $brand = Brand::create($this->attributes($data));

            $this->syncLogo($brand, $data, $actor);

            return $brand;
        });
    }

    /**
     * @param  array{name: string, slug?: string|null, show_in_marquee?: bool, is_active?: bool, logo?: string|null}  $data
     */
    public function update(Brand $brand, array $data, User $actor): Brand
    {
        return DB::transaction(function () use ($brand, $data, $actor) {
            $brand->update($this->attributes($data));

            $this->syncLogo($brand, $data, $actor);

            return $brand;
        });
    }

    public function setInMarquee(Brand $brand, bool $show): Brand
    {
        $brand->update(['show_in_marquee' => $show]);

        return $brand;
    }

    /**
     * @param  list<int>  $ids  every brand id, in the new marquee order
     */
    public function reorder(array $ids, User $actor): void
    {
        Brand::reorder($ids);

        activity('brands')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('marquee reordered');
    }

    public function delete(Brand $brand): void
    {
        $cars = Car::withTrashed()->where('brand_id', $brand->id)->count();

        if ($cars > 0) {
            throw CatalogException::brandInUse($cars);
        }

        DB::transaction(fn () => $brand->delete());
    }

    /**
     * A blank slug means "keep it" on update and "generate from the name" on create.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::except($data, filled($data['slug'] ?? null) ? ['logo'] : ['logo', 'slug']);
    }

    /**
     * @param  array{logo?: string|null}  $data
     */
    private function syncLogo(Brand $brand, array $data, User $actor): void
    {
        if (array_key_exists('logo', $data)) {
            $this->media->sync($brand, 'brand.logo', array_filter([$data['logo']]), actor: $actor);
        }
    }
}
