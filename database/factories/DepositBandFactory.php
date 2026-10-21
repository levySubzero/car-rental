<?php

namespace Database\Factories;

use App\Models\DepositBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositBand>
 */
class DepositBandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'min_age' => 24,
            'max_age' => 70,
            'percent_bp' => 200,
            'is_active' => true,
        ];
    }
}
