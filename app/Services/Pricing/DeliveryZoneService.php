<?php

namespace App\Services\Pricing;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery zones (M6.6). At most one zone is the default: marking one clears the others.
 */
class DeliveryZoneService
{
    /**
     * @return Collection<int, DeliveryZone>
     */
    public function all(): Collection
    {
        return DeliveryZone::query()->ordered()->get();
    }

    /**
     * @param  array{name: string, description: string|null, fee_minor: int|null, is_default: bool, is_active: bool}  $data
     */
    public function create(array $data): DeliveryZone
    {
        return DB::transaction(function () use ($data) {
            $zone = DeliveryZone::create($this->normalise($data));
            $this->keepSingleDefault($zone);

            return $zone;
        });
    }

    /**
     * @param  array{name: string, description: string|null, fee_minor: int|null, is_default: bool, is_active: bool}  $data
     */
    public function update(DeliveryZone $zone, array $data): DeliveryZone
    {
        return DB::transaction(function () use ($zone, $data) {
            $zone->update($this->normalise($data));
            $this->keepSingleDefault($zone);

            return $zone;
        });
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids, User $actor): void
    {
        DeliveryZone::reorder($ids);

        activity('delivery-zones')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('delivery zones reordered');
    }

    public function delete(DeliveryZone $zone): void
    {
        $zone->delete();
    }

    /**
     * An inactive zone cannot be the default.
     *
     * @param  array{name: string, description: string|null, fee_minor: int|null, is_default: bool, is_active: bool}  $data
     * @return array{name: string, description: string|null, fee_minor: int|null, is_default: bool, is_active: bool}
     */
    private function normalise(array $data): array
    {
        return [...$data, 'is_default' => $data['is_default'] && $data['is_active']];
    }

    private function keepSingleDefault(DeliveryZone $zone): void
    {
        if ($zone->is_default) {
            DeliveryZone::query()->whereKeyNot($zone->id)->where('is_default', true)->get()
                ->each(fn (DeliveryZone $other) => $other->update(['is_default' => false]));
        }
    }
}
