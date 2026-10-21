<?php

namespace App\Services\Pricing;

use App\Models\Currency;

/**
 * The currencies a renter can switch to (M6.13): the base plus every active currency that
 * has a rate. Prices travel in KES minor units; the browser converts them with `effective`
 * (lib/money.ts), so this is all the client needs.
 *
 * @phpstan-type DisplayCurrency array{code: string, name: string, symbol: string, decimals: int, effective: string|null}
 */
class DisplayCurrencyService
{
    public const COOKIE = 'currency';

    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly FxRateService $fx,
    ) {}

    /**
     * @return list<DisplayCurrency>
     */
    public function options(): array
    {
        $table = $this->fx->table();
        $base = $this->currencies->baseCode();

        return array_values($this->currencies->active()
            ->filter(fn (Currency $currency) => $table->has($currency->code))
            ->map(fn (Currency $currency) => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimals' => $currency->decimals,
                'effective' => $currency->code === $base ? null : $table->get($currency->code)?->effective(),
            ])
            ->all());
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_column($this->options(), 'code');
    }

    /**
     * The renter's choice (from the cookie), if it is still offered; otherwise the base.
     *
     * @param  list<string>|null  $offered
     */
    public function selected(mixed $choice, ?array $offered = null): string
    {
        $offered ??= $this->codes();

        return is_string($choice) && in_array($choice, $offered, true) ? $choice : $this->currencies->baseCode();
    }
}
