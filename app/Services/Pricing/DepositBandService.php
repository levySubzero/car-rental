<?php

namespace App\Services\Pricing;

use App\Models\DepositBand;
use Illuminate\Database\Eloquent\Collection;

/**
 * Security deposit bands by age (M6.7). SaveDepositBandRequest refuses overlapping active bands.
 */
class DepositBandService
{
    /**
     * @return Collection<int, DepositBand>
     */
    public function all(): Collection
    {
        return DepositBand::query()->orderBy('min_age')->get();
    }

    /**
     * @param  array{min_age: int, max_age: int, percent_bp: int, label: string|null, is_active: bool}  $data
     */
    public function create(array $data): DepositBand
    {
        return DepositBand::create($data);
    }

    /**
     * @param  array{min_age: int, max_age: int, percent_bp: int, label: string|null, is_active: bool}  $data
     */
    public function update(DepositBand $band, array $data): DepositBand
    {
        $band->update($data);

        return $band;
    }

    public function delete(DepositBand $band): void
    {
        $band->delete();
    }
}
