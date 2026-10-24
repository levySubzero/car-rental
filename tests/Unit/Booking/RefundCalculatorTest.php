<?php

use App\Services\Booking\RefundCalculator;
use App\Support\Money;
use Carbon\CarbonImmutable;

// D34: the tier is the longest notice the renter still gave; the refund is that share of the
// reservation paid. Less notice than every tier, or a no-show, suggests nothing.
const REFUND_TIERS = [
    ['label' => 'Two days or more', 'hours_before_pickup' => 48, 'refund_percent_bp' => 10_000],
    ['label' => 'A day or more', 'hours_before_pickup' => 24, 'refund_percent_bp' => 5_000],
];

function suggestRefund(int $minutesBeforePickup, bool $noShow = false, array $tiers = REFUND_TIERS): array
{
    $pickup = CarbonImmutable::parse('2026-11-10 10:00', 'UTC');

    return (new RefundCalculator)
        ->suggest($tiers, $pickup, $pickup->subMinutes($minutesBeforePickup), new Money(1_658_800), $noShow)
        ->toArray();
}

it('picks the longest notice the renter met', function (int $minutes, ?string $label, int $percentBp, int $amountMinor) {
    $suggestion = suggestRefund($minutes);

    expect($suggestion['tier']['label'] ?? null)->toBe($label)
        ->and($suggestion['percent_bp'])->toBe($percentBp)
        ->and($suggestion['amount']->minor)->toBe($amountMinor);
})->with([
    'well ahead' => [72 * 60, 'Two days or more', 10_000, 1_658_800],
    'exactly 48 h' => [48 * 60, 'Two days or more', 10_000, 1_658_800],
    'a minute short of 48 h' => [48 * 60 - 1, 'A day or more', 5_000, 829_400],
    'exactly 24 h' => [24 * 60, 'A day or more', 5_000, 829_400],
    'below every tier' => [24 * 60 - 1, null, 0, 0],
    'after pickup' => [-60, null, 0, 0],
]);

it('reports the notice in whole hours', function () {
    expect(suggestRefund(25 * 60 + 59)['notice_hours'])->toBe(25)
        ->and(suggestRefund(-120)['notice_hours'])->toBe(0);
});

it('suggests nothing for a no-show, whatever the notice', function () {
    $suggestion = suggestRefund(72 * 60, noShow: true);

    expect($suggestion['tier'])->toBeNull()->and($suggestion['amount']->minor)->toBe(0);
});

it('suggests nothing without tiers', function () {
    expect(suggestRefund(72 * 60, tiers: [])['amount']->minor)->toBe(0);
});

it('rounds a share of the reservation half up', function () {
    $pickup = CarbonImmutable::parse('2026-11-10 10:00', 'UTC');
    $suggestion = (new RefundCalculator)->suggest(
        [['label' => null, 'hours_before_pickup' => 0, 'refund_percent_bp' => 3_333]],
        $pickup, $pickup->subHour(), new Money(1_658_850),
    );

    // 1,658,850 × 33.33% = 552,894.705 → 552,895
    expect($suggestion->amount->minor)->toBe(552_895);
});
