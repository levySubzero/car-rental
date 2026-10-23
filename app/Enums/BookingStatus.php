<?php

namespace App\Enums;

/**
 * A booking's lifecycle (PRD §5, D3). Every change goes through BookingStatusService, which
 * checks transitions(). Whether a car is blocked is decided by availability blocks (D35).
 */
enum BookingStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentSubmitted = 'payment_submitted';
    case AwaitingCash = 'awaiting_cash';
    case PaymentRejected = 'payment_rejected';
    case Reserved = 'reserved';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    /**
     * The statuses this one may move to.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::AwaitingPayment, self::Expired],
            self::AwaitingPayment => [self::PaymentSubmitted, self::AwaitingCash, self::Expired],
            self::PaymentSubmitted => [self::Reserved, self::PaymentRejected],
            self::AwaitingCash => [self::Reserved, self::Expired],
            self::PaymentRejected => [self::PaymentSubmitted, self::Cancelled],
            self::Reserved => [self::Active, self::Cancelled, self::NoShow],
            self::Active => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow, self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->transitions() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingPayment => 'Awaiting payment',
            self::PaymentSubmitted => 'Payment submitted',
            self::AwaitingCash => 'Awaiting cash',
            self::PaymentRejected => 'Payment rejected',
            self::Reserved => 'Reserved',
            self::Active => 'On hire',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
            self::Expired => 'Expired',
        };
    }
}
