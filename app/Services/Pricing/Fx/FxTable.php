<?php

namespace App\Services\Pricing\Fx;

use App\Exceptions\Domain\MissingFxRateException;
use App\Support\Decimal;
use App\Support\Money;

/**
 * The exchange rates in force at one moment, keyed by currency code. Quotes convert with
 * this table and store it in their snapshot, so a quote never re-reads the database.
 * All amounts are 2-decimal minor units (D27); conversions round half up to the cent.
 */
final readonly class FxTable
{
    /**
     * @param  array<string, ResolvedRate>  $rates  non-base currencies only
     */
    public function __construct(
        public string $base,
        public array $rates,
    ) {}

    public function has(string $code): bool
    {
        return $code === $this->base || isset($this->rates[$code]);
    }

    public function get(string $code): ?ResolvedRate
    {
        return $this->rates[$code] ?? null;
    }

    /**
     * A foreign amount in the base currency at the market rate: USD 50 at 129 → KES 6,450.
     */
    public function toBase(Money $amount): Money
    {
        if ($amount->currency === $this->base) {
            return $amount;
        }

        $rate = $this->rates[$amount->currency] ?? throw new MissingFxRateException($amount->currency);

        return new Money(Decimal::toInt(Decimal::mul((string) $amount->minor, $rate->market)), $this->base);
    }

    /**
     * A base-currency amount shown in another currency: amount ÷ (market − margin).
     */
    public function display(Money $amount, string $code): Money
    {
        if ($code === $this->base) {
            return $amount;
        }

        $rate = $this->rates[$code] ?? throw new MissingFxRateException($code);

        return new Money(Decimal::toInt(Decimal::div((string) $amount->minor, $rate->effective())), $code);
    }

    /**
     * @return array{base: string, rates: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'rates' => array_map(fn (ResolvedRate $rate) => $rate->toArray(), $this->rates),
        ];
    }
}
