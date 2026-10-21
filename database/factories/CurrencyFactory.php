<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z]{3}'),
            'name' => fake()->words(2, true),
            'symbol' => fake()->randomLetter(),
            'decimals' => 2,
            'is_active' => true,
        ];
    }

    /**
     * A well-known currency, e.g. ->code('USD').
     */
    public function code(string $code, int $decimals = 2): self
    {
        $names = ['KES' => ['Kenya Shilling', 'KSh'], 'USD' => ['US Dollar', '$'], 'GBP' => ['British Pound', '£'], 'EUR' => ['Euro', '€'], 'AED' => ['UAE Dirham', 'AED']];

        return $this->state([
            'code' => $code,
            'name' => $names[$code][0] ?? $code,
            'symbol' => $names[$code][1] ?? $code,
            'decimals' => $decimals,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
