<?php

namespace Database\Factories;

use App\Enums\AvailabilityBlockType;
use App\Enums\BlackoutReason;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityBlock>
 */
class AvailabilityBlockFactory extends Factory
{
    /**
     * A staff block by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->startOfHour();

        return [
            'car_id' => Car::factory(),
            'type' => AvailabilityBlockType::Blocked,
            'starts_at' => $start,
            'ends_at' => $start->addDays(2),
            'reason' => BlackoutReason::Maintenance,
            'notes' => null,
        ];
    }

    public function hold(?Booking $booking = null, int $minutes = 120): static
    {
        return $this->state(fn () => [
            'type' => AvailabilityBlockType::Hold,
            'booking_id' => $booking->id ?? Booking::factory(),
            'expires_at' => now()->addMinutes($minutes),
            'reason' => null,
        ]);
    }

    public function booked(?Booking $booking = null): static
    {
        return $this->state(fn () => [
            'type' => AvailabilityBlockType::Booked,
            'booking_id' => $booking->id ?? Booking::factory(),
            'expires_at' => null,
            'reason' => null,
        ]);
    }
}
