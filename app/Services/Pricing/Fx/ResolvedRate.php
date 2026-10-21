<?php

namespace App\Services\Pricing\Fx;

use App\Enums\FxRateSource;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * The rate that applies to a currency right now (D28), with the display margin applied.
 *
 * `market` converts foreign charges into KES (PRD: chauffeur at the market rate).
 * `effective` = market − margin converts KES prices for display: amount ÷ effective.
 */
final readonly class ResolvedRate
{
    public function __construct(
        public string $code,
        public string $market,
        public string $margin,
        public FxRateSource $source,
        public CarbonImmutable $fetchedAt,
        public bool $stale,
        public ?CarbonImmutable $overrideUntil = null,
    ) {}

    public function effective(): string
    {
        return Decimal::round(Decimal::sub($this->market, $this->margin), 6);
    }

    /**
     * @return array{code: string, market: string, margin: string, effective: string, source: string, fetched_at: string, stale: bool, override_until: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'market' => $this->market,
            'margin' => $this->margin,
            'effective' => $this->effective(),
            'source' => $this->source->value,
            'fetched_at' => $this->fetchedAt->toIso8601String(),
            'stale' => $this->stale,
            'override_until' => $this->overrideUntil?->toIso8601String(),
        ];
    }

    /**
     * @param  array{code: string, market: string, margin: string, source: string, fetched_at: string, stale: bool, override_until?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            market: $data['market'],
            margin: $data['margin'],
            source: FxRateSource::from($data['source']),
            fetchedAt: CarbonImmutable::parse($data['fetched_at']),
            stale: $data['stale'],
            overrideUntil: isset($data['override_until']) ? CarbonImmutable::parse($data['override_until']) : null,
        );
    }
}
