<?php

namespace Tests\Support;

use App\Enums\FxRateSource;
use App\Models\Currency;
use App\Models\FxRate;

/**
 * The display currencies from the seed pack, and rates for them.
 */
final class PricingFixtures
{
    /**
     * KES (base, 0 display decimals), USD, GBP, EUR and AED, all active.
     */
    public static function currencies(): void
    {
        foreach ([['KES', 0], ['USD', 2], ['GBP', 2], ['EUR', 2], ['AED', 2]] as [$code, $decimals]) {
            if (! Currency::query()->where('code', $code)->exists()) {
                Currency::factory()->code($code, $decimals)->create();
            }
        }
    }

    /**
     * Fresh API rates, KES per unit. USD 129 is the PRD worked example.
     *
     * @param  array<string, string>  $rates
     */
    public static function rates(array $rates = ['USD' => '129', 'GBP' => '163.5', 'EUR' => '140.25', 'AED' => '35.12']): void
    {
        foreach ($rates as $code => $rate) {
            FxRate::factory()->create(['currency_code' => $code, 'market_rate' => $rate, 'source' => FxRateSource::Api, 'fetched_at' => now()->subMinutes(5)]);
        }
    }
}
