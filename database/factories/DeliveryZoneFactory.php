<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'fee_minor' => 200_000,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function quoted(): self
    {
        return $this->state(['fee_minor' => null]);
    }
}
