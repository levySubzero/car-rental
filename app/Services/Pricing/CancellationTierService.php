<?php

namespace App\Services\Pricing;

use App\Models\CancellationTier;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cancellation refund tiers (M6.8), longest notice first.
 */
class CancellationTierService
{
    /**
     * @return Collection<int, CancellationTier>
     */
    public function all(): Collection
    {
        return CancellationTier::query()->orderByDesc('hours_before_pickup')->get();
    }

    /**
     * @param  array{hours_before_pickup: int, refund_percent_bp: int, label: string|null}  $data
     */
    public function create(array $data): CancellationTier
    {
        return CancellationTier::create($data);
    }

    /**
     * @param  array{hours_before_pickup: int, refund_percent_bp: int, label: string|null}  $data
     */
    public function update(CancellationTier $tier, array $data): CancellationTier
    {
        $tier->update($data);

        return $tier;
    }

    public function delete(CancellationTier $tier): void
    {
        $tier->delete();
    }
}
