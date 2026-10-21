<?php

namespace Database\Factories;

use App\Enums\FxRateSource;
use App\Models\FxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FxRate>
 */
class FxRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_code' => 'USD',
            'market_rate' => '129.000000',
            'source' => FxRateSource::Api,
            'provider' => 'open_exchange_rates',
            'fetched_at' => now(),
        ];
    }

    public function manual(): self
    {
        return $this->state(['source' => FxRateSource::Manual, 'provider' => null]);
    }
}
