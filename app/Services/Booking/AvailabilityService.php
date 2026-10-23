<?php

namespace App\Services\Booking;

use App\Enums\AvailabilityBlockType;
use App\Exceptions\Domain\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Car;
use App\Settings\PricingSettings;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Whether a car is free (D35). Every reason a car is taken is an availability block: a
 * booking's hold, a reserved booking or a staff block. The turnaround buffer keeps a gap
 * between one block and the next.
 */
class AvailabilityService
{
    public function __construct(private readonly PricingSettings $pricing) {}

    /**
     * The blocks that stop $car being booked for $range.
     *
     * @return Collection<int, AvailabilityBlock>
     */
    public function conflicts(Car $car, DateRange $range, ?Booking $ignore = null): Collection
    {
        return $this->conflicting($car, $range, $ignore)->get();
    }

    public function isAvailable(Car $car, DateRange $range, ?Booking $ignore = null): bool
    {
        return ! $this->conflicting($car, $range, $ignore)->exists();
    }

    /**
     * Hold the car for a submitted booking until it is paid for or expires. The car row is
     * locked first, so two renters submitting the same dates queue up and only one wins.
     */
    public function lockAndHold(Booking $booking): AvailabilityBlock
    {
        return DB::transaction(function () use ($booking) {
            $car = Car::withTrashed()->whereKey($booking->car_id)->lockForUpdate()->firstOrFail();

            if (! $this->isAvailable($car, $booking->range(), $booking)) {
                throw BookingException::carUnavailable();
            }

            $this->release($booking);

            return $booking->availabilityBlocks()->create([
                'car_id' => $car->id,
                'type' => AvailabilityBlockType::Hold,
                'starts_at' => $booking->pickup_at,
                'ends_at' => $booking->return_at,
                'expires_at' => now()->addMinutes($this->pricing->hold_minutes),
            ]);
        });
    }

    /**
     * Turn the booking's hold into a firm booking (M9.7, on Reserved).
     */
    public function confirm(Booking $booking): void
    {
        $booking->availabilityBlocks()->update(['type' => AvailabilityBlockType::Booked->value, 'expires_at' => null]);
    }

    public function release(Booking $booking): void
    {
        $booking->availabilityBlocks()->delete();
    }

    /**
     * @return Builder<AvailabilityBlock>
     */
    private function conflicting(Car $car, DateRange $range, ?Booking $ignore): Builder
    {
        $buffer = $this->pricing->turnaround_minutes;

        return AvailabilityBlock::query()
            ->where('car_id', $car->id)
            ->active()
            ->overlapping($range->pad($buffer, $buffer))
            ->when($ignore !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('booking_id')
                ->orWhere('booking_id', '!=', $ignore?->id)));
    }
}
