<?php

namespace App\Services\Pricing\Fx;

/**
 * A source of market exchange rates against the base currency (KES).
 */
interface FxRateProvider
{
    /**
     * Rates for the given currency codes. Returns an empty result when the provider
     * fetches nothing (manual). Throws FxProviderException when the source fails or
     * returns anything it cannot vouch for.
     *
     * @param  list<string>  $codes
     */
    public function fetch(string $base, array $codes): FxFetchResult;

    public function name(): string;
}
