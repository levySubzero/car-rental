<?php

namespace App\Services\Pricing;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Extras on top of the daily rate (M6.5). Deleting one is safe: bookings snapshot their quote.
 */
class ChargeService
{
    /**
     * @return Collection<int, Charge>
     */
    public function all(): Collection
    {
        return Charge::query()->ordered()->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Charge
    {
        return Charge::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Charge $charge, array $data): Charge
    {
        $charge->update($data);

        return $charge;
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids, User $actor): void
    {
        Charge::reorder($ids);

        activity('charges')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('charges reordered');
    }

    public function delete(Charge $charge): void
    {
        $charge->delete();
    }
}
