<?php

namespace App\Services\Pricing\Quote;

use App\Models\DepositBand;
use App\Services\Pricing\Fx\FxTable;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * A priced rental (TECHNICAL_PLAN §10). Everything a booking snapshots at submit (D8):
 * the lines, the totals, the FX table and the settings it was worked out with.
 */
final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     * @param  array<string, array{total: Money, reservation: Money, balance: Money, deposit: Money|null}>  $display
     */
    public function __construct(
        public CarbonImmutable $pickup,
        public CarbonImmutable $return,
        public int $days,
        public array $lines,
        public Money $rental,
        public Money $total,
        public Money $reservation,
        public Money $balance,
        public ?Money $deposit,
        public ?DepositBand $band,
        public ?int $driverAge,
        public ?int $mileageKmPerDay,
        public bool $requiresStaffQuote,
        public array $display,
        public FxTable $fx,
        public QuoteSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pickup_at' => $this->pickup->toIso8601String(),
            'return_at' => $this->return->toIso8601String(),
            'days' => $this->days,
            'lines' => array_map(fn (QuoteLine $line) => $line->toArray(), $this->lines),
            'rental' => $this->rental,
            'total' => $this->total,
            'reservation' => $this->reservation,
            'balance' => $this->balance,
            'deposit' => $this->deposit,
            'deposit_band' => $this->band === null ? null : [
                'min_age' => $this->band->min_age,
                'max_age' => $this->band->max_age,
                'percent_bp' => $this->band->percent_bp,
            ],
            'driver_age' => $this->driverAge,
            // Null means unlimited.
            'mileage_km_per_day' => $this->mileageKmPerDay,
            'requires_staff_quote' => $this->requiresStaffQuote,
            'display' => $this->display,
            'fx' => $this->fx->toArray(),
            'settings' => $this->settings->toArray(),
            'settings_hash' => $this->settings->hash(),
        ];
    }
}
