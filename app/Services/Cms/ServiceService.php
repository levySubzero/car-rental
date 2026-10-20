<?php

namespace App\Services\Cms;

use App\Models\Service;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The services list on the home and services pages (PRD A-2, H-5).
 *
 * @phpstan-type ServiceData array{title: string, slug?: string|null, summary: string, body?: string|null, cta_type: string, cta_value?: string|null, is_active?: bool, image?: string|null}
 */
class ServiceService
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * Every service in display order. The list is short, so it is not paginated.
     *
     * @return Collection<int, Service>
     */
    public function ordered(): Collection
    {
        return Service::query()->with('image')->ordered()->get();
    }

    /**
     * @param  ServiceData  $data
     */
    public function create(array $data, User $actor): Service
    {
        return DB::transaction(function () use ($data, $actor) {
            $service = Service::create($this->attributes($data));

            $this->syncImage($service, $data, $actor);

            return $service;
        });
    }

    /**
     * @param  ServiceData  $data
     */
    public function update(Service $service, array $data, User $actor): Service
    {
        return DB::transaction(function () use ($service, $data, $actor) {
            $service->update($this->attributes($data));

            $this->syncImage($service, $data, $actor);

            return $service;
        });
    }

    public function setActive(Service $service, bool $active): Service
    {
        $service->update(['is_active' => $active]);

        return $service;
    }

    /**
     * @param  list<int>  $ids  every service id, in the new order
     */
    public function reorder(array $ids, User $actor): void
    {
        Service::reorder($ids);

        activity('services')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('services reordered');
    }

    public function delete(Service $service): void
    {
        DB::transaction(fn () => $service->delete());
    }

    /**
     * A blank slug means "keep it" on update and "generate from the title" on create.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::except($data, filled($data['slug'] ?? null) ? ['image'] : ['image', 'slug']);
    }

    /**
     * @param  array{image?: string|null}  $data
     */
    private function syncImage(Service $service, array $data, User $actor): void
    {
        if (array_key_exists('image', $data)) {
            $this->media->sync($service, 'service.image', array_filter([$data['image']]), actor: $actor);
        }
    }
}
