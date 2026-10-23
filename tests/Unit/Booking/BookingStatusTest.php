<?php

use App\Enums\BookingStatus as S;

// The PRD §5 lifecycle table: every edge not listed here is refused.
const ALLOWED_BOOKING_EDGES = [
    'draft' => ['awaiting_payment', 'expired'],
    'awaiting_payment' => ['payment_submitted', 'awaiting_cash', 'expired'],
    'payment_submitted' => ['reserved', 'payment_rejected'],
    'awaiting_cash' => ['reserved', 'expired'],
    'payment_rejected' => ['payment_submitted', 'cancelled'],
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
