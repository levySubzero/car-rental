<?php

namespace App\Services\Pricing\Fx;

use Carbon\CarbonImmutable;

final readonly class FxFetchResult
{
    /**
     * @param  array<string, string>  $rates  base-currency units per unit of each code, 6 places
     */
    public function __construct(
        public array $rates,
        public ?CarbonImmutable $asOf,
    ) {}

    public static function empty(): self
    {
        return new self([], null);
    }

    public function isEmpty(): bool
    {
        return $this->rates === [];
    }
}
