<?php

use App\Enums\BookingStatus as S;

// The PRD §5 lifecycle table, plus cash on a retry and a lapsed retry (D38) and staff
// cancelling an unpaid booking (D41): every edge not listed here is refused.
const ALLOWED_BOOKING_EDGES = [
    'draft' => ['awaiting_payment', 'expired'],
    'awaiting_payment' => ['payment_submitted', 'awaiting_cash', 'cancelled', 'expired'],
    'payment_submitted' => ['reserved', 'payment_rejected', 'cancelled'],
    'awaiting_cash' => ['reserved', 'cancelled', 'expired'],
    'payment_rejected' => ['payment_submitted', 'awaiting_cash', 'cancelled', 'expired'],
    'reserved' => ['active', 'cancelled', 'no_show'],
    'active' => ['completed'],
    'completed' => [],
    'cancelled' => [],
    'no_show' => [],
    'expired' => [],
];

it('allows exactly the lifecycle edges', function (S $from) {
    foreach (S::cases() as $to) {
        $allowed = in_array($to->value, ALLOWED_BOOKING_EDGES[$from->value], true);

        expect($from->canTransitionTo($to))->toBe($allowed, "{$from->value} → {$to->value}");
    }
})->with(S::cases());

it('marks the end states as terminal', function () {
    $terminal = array_values(array_filter(S::cases(), fn (S $status) => $status->isTerminal()));

    expect($terminal)->toBe([S::Completed, S::Cancelled, S::NoShow, S::Expired]);
});
