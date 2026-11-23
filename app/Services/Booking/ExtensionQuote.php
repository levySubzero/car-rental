<?php

namespace App\Services\Booking;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * What extending a hire to a new return costs (D50): the whole longer stay priced at today's
 * rates, less the stay as it now stands. `snapshot` is what the extension booking freezes.
 */
final readonly class ExtensionQuote
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $extraDays,
        public int $chainDays,
        public Money $amount,
        public array $snapshot,
    ) {}

    /**
     * @return array{from: string, to: string, extra_days: int, chain_days: int, amount: Money, lines: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'extra_days' => $this->extraDays,
            'chain_days' => $this->chainDays,
            'amount' => $this->amount,
            'lines' => $this->snapshot['lines'],
        ];
    }
}
