<?php

namespace App\Services\Pricing\Fx;

/**
 * Staff enter every rate on the exchange rates page; there is nothing to fetch.
 */
class ManualProvider implements FxRateProvider
{
    public function fetch(string $base, array $codes): FxFetchResult
    {
        return FxFetchResult::empty();
    }

    public function name(): string
    {
        return 'manual';
    }
}
