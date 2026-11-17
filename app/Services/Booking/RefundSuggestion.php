<?php

namespace App\Services\Booking;

use App\Support\Money;

/**
 * What RefundCalculator suggests for a cancellation or no-show (D34). `amount` is the tier's
 * share of the reservation; the transaction cost (`fee`, paid by the client) comes off it, and
 * `net()` is what goes back.
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
        public Money $fee = new Money(0),
    ) {}

    public function withFee(Money $fee): self
    {
        return new self($this->noticeHours, $this->tier, $this->percentBp, $this->amount, $fee->minor > $this->amount->minor ? $this->amount : $fee);
    }

    public function net(): Money
    {
        return $this->amount->subtract($this->fee);
    }

    /**
     * @return array{notice_hours: int, tier: Tier|null, percent_bp: int, amount: Money, fee: Money, net: Money}
     */
    public function toArray(): array
    {
        return [
            'notice_hours' => $this->noticeHours,
            'tier' => $this->tier,
            'percent_bp' => $this->percentBp,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'net' => $this->net(),
        ];
    }
}
