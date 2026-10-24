<?php

namespace App\Services\Booking;

use App\Enums\RoundingMode;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * The refund a cancellation suggests (D34), with no database access. The tier is the one with
 * the longest notice the renter still gave, counted in hours before pickup; the refund is that
 * share of the reservation paid. Less notice than every tier, or a no-show, suggests nothing.
 * Tiers come from the booking's own snapshot, never the live table.
 */
final class RefundCalculator
{
    /**
     * @param  list<array{label?: string|null, hours_before_pickup: int, refund_percent_bp: int}>  $tiers
     */
    public function suggest(array $tiers, CarbonInterface $pickupAt, CarbonInterface $at, Money $reservationPaid, bool $noShow = false): RefundSuggestion
    {
        $noticeMinutes = max(0, (int) floor($at->diffInMinutes($pickupAt, false)));
        $noticeHours = intdiv($noticeMinutes, 60);

        $tier = null;

        if (! $noShow) {
            foreach ($tiers as $candidate) {
                $met = $noticeMinutes >= $candidate['hours_before_pickup'] * 60;

                if ($met && ($tier === null || $candidate['hours_before_pickup'] > $tier['hours_before_pickup'])) {
                    $tier = [
                        'label' => $candidate['label'] ?? null,
                        'hours_before_pickup' => (int) $candidate['hours_before_pickup'],
                        'refund_percent_bp' => (int) $candidate['refund_percent_bp'],
                    ];
                }
            }
        }

        $percentBp = $tier['refund_percent_bp'] ?? 0;

        return new RefundSuggestion($noticeHours, $tier, $percentBp, $reservationPaid->percentBp($percentBp, RoundingMode::Round));
    }
}
