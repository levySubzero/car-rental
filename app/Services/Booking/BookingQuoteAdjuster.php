<?php

namespace App\Services\Booking;

use App\Enums\RoundingMode;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Services\Pricing\Fx\FxTable;
use App\Services\Pricing\Quote\QuoteLine;
use App\Support\Money;

/**
 * Staff setting a booking's price or deposit by hand, e.g. a one-day rental. The columns and
 * the quote snapshot move together, because the renter's page and delivery changes read the
 * snapshot. The difference shows as its own line, and the original figures are kept.
 */
class BookingQuoteAdjuster
{
    /**
     * Fills the booking without saving it; null leaves a figure as it is.
     *
     * @return array{total_minor: int|null, security_deposit_minor: int|null}|null the figures before, or null if nothing changed
     */
    public function adjust(Booking $booking, ?Money $total, ?Money $deposit): ?array
    {
        $changesTotal = $total !== null && $total->minor !== (int) $booking->total_minor;
        $changesDeposit = $deposit !== null && $deposit->minor !== $booking->security_deposit_minor;

        if (! $changesTotal && ! $changesDeposit) {
            return null;
        }

        $before = ['total_minor' => $booking->total_minor, 'security_deposit_minor' => $booking->security_deposit_minor];
        $quote = $booking->quote ?? [];
        $quote['staff_adjustment'] ??= $before;

        if ($changesTotal) {
            $quote = $this->withTotal($booking, $quote, $total);
        }

        if ($changesDeposit) {
            $quote['deposit'] = $deposit;
            $booking->security_deposit_minor = $deposit->minor;
        }

        $quote['display'] = $this->display($quote);
        $booking->quote = $quote;

        return $before;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function withTotal(Booking $booking, array $quote, Money $total): array
    {
        // A delivery fee staff set earlier sits on top of the snapshot's total.
        $quoted = (int) $quote['total']['minor'];
        $snapshotTotal = new Money($total->minor - ((int) $booking->total_minor - $quoted));

        if ($snapshotTotal->minor < 0) {
            throw BookingException::adjustedTotalTooLow();
        }

        $reservation = $snapshotTotal->percentBp(
            (int) $quote['settings']['reservation_percent_bp'],
            RoundingMode::from($quote['settings']['reservation_rounding']),
        );
        $difference = new Money($snapshotTotal->minor - $quoted);

        $quote['lines'][] = (new QuoteLine(QuoteLine::KIND_CHARGE, 'staff_adjustment', 'Price adjustment', 1, $difference, $difference, 'reservation', true))->toArray();
        $quote['total'] = $snapshotTotal;
        $quote['reservation'] = $reservation;
        $quote['balance'] = $snapshotTotal->subtract($reservation);

        $booking->forceFill([
            'total_minor' => $total->minor,
            'reservation_minor' => $reservation->minor,
            'balance_minor' => $total->minor - $reservation->minor,
        ]);

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, array<string, Money|null>>
     */
    private function display(array $quote): array
    {
        $fx = FxTable::fromArray($quote['fx']);
        $figure = fn (mixed $money): ?Money => $money === null
            ? null
            : ($money instanceof Money ? $money : new Money((int) $money['minor']));
        $display = [];

        foreach (array_keys($fx->rates) as $code) {
            foreach (['total', 'reservation', 'balance', 'deposit'] as $key) {
                $amount = $figure($quote[$key] ?? null);
                $display[$code][$key] = $amount === null ? null : $fx->display($amount, $code);
            }
        }

        return $display;
    }
}
