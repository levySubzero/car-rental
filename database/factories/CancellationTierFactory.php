<?php

namespace Database\Factories;

use App\Models\CancellationTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationTier>
 */
class CancellationTierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hours_before_pickup' => fake()->unique()->numberBetween(1, 720),
            'refund_percent_bp' => 5_000,
        ];
    }
}
