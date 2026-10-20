<?php

namespace App\Services\Catalog;

use App\Exceptions\Domain\CatalogException;
use App\Models\Car;
use App\Models\CarBlackout;
use App\Models\User;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Periods a car is off the road. Clashes with bookings are checked by AvailabilityService
 * from M8; here a car only refuses two overlapping blackouts.
 */
class CarBlackoutService
{
    /**
     * @param  array{starts_at: string, ends_at: string, reason: string, notes?: string|null}  $data
     */
    public function create(Car $car, array $data, User $actor): CarBlackout
    {
        return DB::transaction(function () use ($car, $data, $actor) {
            $this->ensureNoOverlap($car, $this->rangeOf($data));

            return $car->blackouts()->create([...$data, 'created_by' => $actor->id]);
        });
    }

    /**
     * @param  array{starts_at: string, ends_at: string, reason: string, notes?: string|null}  $data
     */
    public function update(CarBlackout $blackout, array $data): CarBlackout
    {
        return DB::transaction(function () use ($blackout, $data) {
            $this->ensureNoOverlap($blackout->car, $this->rangeOf($data), $blackout);

            $blackout->update($data);

            return $blackout;
        });
    }

    public function delete(CarBlackout $blackout): void
    {
        $blackout->delete();
    }

    private function ensureNoOverlap(Car $car, DateRange $range, ?CarBlackout $ignore = null): void
    {
        $clash = $car->blackouts()
            ->overlapping($range)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore?->id))
            ->lockForUpdate()
            ->exists();

        if ($clash) {
            throw CatalogException::blackoutOverlaps();
        }
    }

    /**
     * @param  array{starts_at: string, ends_at: string}  $data
     */
    private function rangeOf(array $data): DateRange
    {
        return new DateRange(CarbonImmutable::parse($data['starts_at']), CarbonImmutable::parse($data['ends_at']));
    }
}
