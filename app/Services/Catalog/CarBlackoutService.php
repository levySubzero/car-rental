<?php

namespace App\Services\Catalog;

use App\Enums\AvailabilityBlockType;
use App\Exceptions\Domain\CatalogException;
use App\Models\AvailabilityBlock;
use App\Models\Car;
use App\Models\User;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Periods staff take a car off the road: `blocked` availability blocks (D35). A block may not
 * overlap another one, or a booking's hold or reservation: move or cancel the booking first.
 */
class CarBlackoutService
{
    /**
     * @param  array{starts_at: string, ends_at: string, reason: string, notes?: string|null}  $data
     */
    public function create(Car $car, array $data, User $actor): AvailabilityBlock
    {
        return DB::transaction(function () use ($car, $data, $actor) {
            $this->ensureNoOverlap($car, $this->rangeOf($data));

            return $car->blackouts()->create([...$data, 'created_by' => $actor->id]);
        });
    }

    /**
     * @param  array{starts_at: string, ends_at: string, reason: string, notes?: string|null}  $data
     */
    public function update(AvailabilityBlock $blackout, array $data): AvailabilityBlock
    {
        return DB::transaction(function () use ($blackout, $data) {
            $this->ensureNoOverlap($blackout->car, $this->rangeOf($data), $blackout);

            $blackout->update($data);

            return $blackout;
        });
    }

    public function delete(AvailabilityBlock $blackout): void
    {
        $blackout->delete();
    }

    private function ensureNoOverlap(Car $car, DateRange $range, ?AvailabilityBlock $ignore = null): void
    {
        $clash = $car->blackouts()
            ->overlapping($range)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore?->id))
            ->lockForUpdate()
            ->exists();

        if ($clash) {
            throw CatalogException::blackoutOverlaps();
        }

        $booking = $car->availabilityBlocks()
            ->where('type', '!=', AvailabilityBlockType::Blocked->value)
            ->active()
            ->overlapping($range)
            ->with('booking:id,reference')
            ->first()?->booking;

        if ($booking !== null) {
            throw CatalogException::blackoutOverlapsBooking($booking->reference);
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
