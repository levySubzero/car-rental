<?php

namespace App\Services\Booking;

use App\Support\Money;

/**
 * What RefundCalculator suggests for a cancellation or no-show (D34).
 *
 * @phpstan-type Tier array{label: string|null, hours_before_pickup: int, refund_percent_bp: int}
 */
final readonly class RefundSuggestion
{
    /**
     * @param  Tier|null  $tier  the tier the renter met, null when none
     */
    public function __construct(
        public int $noticeHours,
        public ?array $tier,
        public int $percentBp,
        public Money $amount,
    ) {}

    /**
     * @return array{notice_hours: int, tier: Tier|null, percent_bp: int, amount: Money}
     */
    public function toArray(): array
    {
        return [
            'notice_hours' => $this->noticeHours,
            'tier' => $this->tier,
            'percent_bp' => $this->percentBp,
            'amount' => $this->amount,
        ];
    }
}
