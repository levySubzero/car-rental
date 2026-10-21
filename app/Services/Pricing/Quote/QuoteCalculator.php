<?php

namespace App\Services\Pricing\Quote;

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Enums\RoundingMode;
use App\Exceptions\Domain\QuoteException;
use App\Models\Charge;
use App\Models\DepositBand;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The quote rules (TECHNICAL_PLAN §10), with no database access: everything comes in
 * through the input and the context, so the same input always gives the same quote.
 * tests/fixtures/quotes.json pins the behaviour.
 */
final class QuoteCalculator
{
    public function calculate(QuoteInput $in, QuoteContext $context): Quote
    {
        if (! $in->return->greaterThan($in->pickup)) {
            throw QuoteException::returnBeforePickup();
        }

        $settings = $context->settings;

        // 1. Billable days.
        $days = $settings->dayCounter()->count($in->pickup, $in->return);

        // 2. Rental = days × daily rate.
        $daily = new Money($in->dailyRateMinor);
        $rental = $daily->multiply($days);
        $lines = [new QuoteLine(QuoteLine::KIND_RENTAL, 'rental', $in->carName, $days, $daily, $rental, ChargePayableAt::Reservation->value, true)];

        // 3. Extras the renter picked, plus the charges on every booking.
        foreach ($context->charges as $charge) {
            if ($this->applies($charge, $in)) {
                $lines[] = $this->chargeLine($charge, $days, $context);
            }
        }

        // 4. Delivery, priced by zone (D29).
        $requiresStaffQuote = false;

        if ($in->zone !== null) {
            $fee = $in->zone->fee();
            $requiresStaffQuote = $fee === null;
            $lines[] = new QuoteLine(
                QuoteLine::KIND_DELIVERY, 'delivery', "Delivery: {$in->zone->name}", 1, $fee, $fee,
                ChargePayableAt::Reservation->value, $fee !== null, $fee === null ? 'Quoted by staff' : null,
            );
        }

        // 5. Total of the counted lines; reservation and balance.
        $total = Money::zero();

        foreach ($lines as $line) {
            if ($line->counted && $line->amount !== null) {
                $total = $total->add($line->amount);
            }
        }

        $reservation = $total->percentBp($settings->reservationPercentBp, $settings->reservationRounding);
        $balance = $total->subtract($reservation);

        // 6. Security deposit from the driver's age at pickup.
        [$age, $band, $deposit] = $this->deposit($in, $context);

        // 7. Mileage allowance: the car's own limit, else the default; none after N days.
        $unlimited = $settings->unlimitedMileageAfterDays !== null && $days >= $settings->unlimitedMileageAfterDays;
        $mileage = $unlimited ? null : ($in->mileageLimitKm ?? $settings->defaultMileageKm);

        // 8. Display conversions for every currency with a rate.
        $display = [];

        foreach (array_keys($context->fx->rates) as $code) {
            $display[$code] = [
                'total' => $context->fx->display($total, $code),
                'reservation' => $context->fx->display($reservation, $code),
                'balance' => $context->fx->display($balance, $code),
                'deposit' => $deposit === null ? null : $context->fx->display($deposit, $code),
            ];
        }

        return new Quote(
            $in->pickup, $in->return, $days, $lines, $rental, $total, $reservation, $balance,
            $deposit, $band, $age, $mileage, $requiresStaffQuote, $display, $context->fx, $settings,
        );
    }

    /**
     * D32: an extra applies when the renter picked its code; an "always" charge applies to
     * every quote; a listed charge never does.
     */
    private function applies(Charge $charge, QuoteInput $in): bool
    {
        return $charge->is_active && match ($charge->applies) {
            ChargeApplies::Extra => in_array($charge->code, $in->extras, true),
            ChargeApplies::Always => true,
            ChargeApplies::Listed => false,
        };
    }

    /**
     * Foreign amounts are multiplied out first, then converted once at the market rate:
     * 3 × USD 50 = USD 150 at 129 = KES 19,350 (PRD §5).
     */
    private function chargeLine(Charge $charge, int $days, QuoteContext $context): QuoteLine
    {
        $unit = $charge->amount();
        $quantity = $charge->basis === ChargeBasis::PerDay ? $days : 1;
        $label = $charge->name;

        if ($unit === null || ! $charge->basis->isQuotable()) {
            return new QuoteLine(
                QuoteLine::KIND_CHARGE, $charge->code, $label, $quantity, $unit, null, $charge->payable_at->value, false,
                $unit === null ? 'On request' : ($charge->basis === ChargeBasis::PerKm ? 'Per km, settled at return' : null),
            );
        }

        $amount = $context->fx->toBase($unit->multiply($quantity));
        $counted = $charge->payable_at !== ChargePayableAt::Counter;

        return new QuoteLine(
            QuoteLine::KIND_CHARGE, $charge->code, $label, $quantity, $unit, $amount, $charge->payable_at->value, $counted,
            $counted ? null : 'Paid at the counter',
        );
    }

    /**
     * @return array{0: int|null, 1: DepositBand|null, 2: Money|null}
     */
    private function deposit(QuoteInput $in, QuoteContext $context): array
    {
        if ($in->driverDob === null) {
            return [null, null, null];
        }

        $age = $this->ageOn($in->driverDob, $in->pickup->setTimezone($context->settings->timezone));
        $band = null;

        foreach ($context->bands as $candidate) {
            if ($candidate->is_active && $age >= $candidate->min_age && $age <= $candidate->max_age) {
                $band = $candidate;
                break;
            }
        }

        if ($band === null) {
            throw QuoteException::driverAge($age);
        }

        $deposit = $in->carValueMinor === null ? null : (new Money($in->carValueMinor))->percentBp($band->percent_bp, RoundingMode::Round);

        return [$age, $band, $deposit];
    }

    /**
     * Whole years on the pickup date. Someone born on 29 February turns a year older on
     * 1 March in non-leap years.
     */
    private function ageOn(CarbonImmutable $dob, CarbonImmutable $date): int
    {
        $age = $date->year - $dob->year;

        if ($date->format('md') < $dob->format('md')) {
            $age--;
        }

        return $age;
    }
}
