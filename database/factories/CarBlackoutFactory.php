<?php

namespace Database\Factories;

use App\Enums\BlackoutReason;
use App\Models\Car;
use App\Models\CarBlackout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarBlackout>
 */
class CarBlackoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->startOfHour();

        return [
            'car_id' => Car::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addDays(2),
            'reason' => BlackoutReason::Maintenance,
            'notes' => null,
        ];
    }
}
