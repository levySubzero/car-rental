<?php

namespace App\Services\Booking;

use App\Enums\ChargePayableAt;
use App\Models\Booking;
use App\Services\Pricing\Quote\Quote;
use App\Services\Pricing\Quote\QuoteLine;
use App\Services\Pricing\QuoteService;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Prices an extension at the margin (D50): the whole stay from the original pick-up to the
 * new return, less the stay to where it now ends, both at today's rates. Long-hire rates
 * keep applying, the hand-over day is not counted twice, a per-booking charge is not taken
 * again, and delivery and the deposit stay with the original booking.
 */
class ExtensionPricer
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly BookingQuoteAdjuster $adjuster,
    ) {}

    public function quote(Booking $root, CarbonImmutable $newReturn): ExtensionQuote
    {
        $from = $root->chainEnd();
        $full = $this->stay($root, $newReturn);
        $current = $this->stay($root, $from);

        $lines = $this->difference($full, $current);
        $amount = new Money(max(0, $full->total->minor - $current->total->minor));
        $rental = new Money(max(0, $full->rental->minor - $current->rental->minor));
        $extraDays = max(0, $full->days - $current->days);

        $snapshot = $full->toArray();
        $snapshot = [
            ...$snapshot,
            'pickup_at' => $from->toIso8601String(),
            'return_at' => $newReturn->toIso8601String(),
            'days' => $extraDays,
            'chain_days' => $full->days,
            'extension_of' => $root->reference,
            'lines' => $lines,
            'rental' => $rental,
            'total' => $amount,
            'reservation' => $amount,
            'balance' => new Money(0),
            'deposit' => null,
            'deposit_band' => null,
            'deposit_range' => null,
            'requires_staff_quote' => false,
        ];
        $snapshot['display'] = $this->adjuster->display($snapshot);

        return new ExtensionQuote($from, $newReturn, $extraDays, $full->days, $amount, $snapshot);
    }

    private function stay(Booking $root, CarbonImmutable $return): Quote
    {
        $root->loadMissing(['car', 'driver']);

        return $this->quotes->quote($root->car, $root->pickup_at, $return, $root->extras ?? [], null, $root->driver?->dob);
    }

    /**
     * Each counted line's growth from the current stay to the longer one: extra rental days,
     * per-day cover, and so on. Lines that do not grow (per-booking charges) drop out.
     *
     * @return list<array<string, mixed>>
     */
    private function difference(Quote $full, Quote $current): array
    {
        $before = [];

        foreach ($current->lines as $line) {
            $before["{$line->kind}:{$line->code}"] = $line;
        }

        $lines = [];

        foreach ($full->lines as $line) {
            if (! $line->counted || $line->amount === null) {
                continue;
            }

            $was = $before["{$line->kind}:{$line->code}"] ?? null;
            $amount = $line->amount->minor - (int) $was?->amount?->minor;

            if ($amount === 0) {
                continue;
            }

            $quantity = max(1, $line->quantity - (int) $was?->quantity);
            $label = $line->kind === QuoteLine::KIND_RENTAL ? "Extra rental ({$quantity} ".($quantity === 1 ? 'day' : 'days').')' : $line->label;

            $lines[] = (new QuoteLine(
                // All of it is paid up front, whatever the original line was payable at.
                $line->kind, $line->code, $label, $quantity, $line->unit, new Money($amount), ChargePayableAt::Reservation->value, true, $line->note,
            ))->toArray();
        }

        return $lines;
    }
}
