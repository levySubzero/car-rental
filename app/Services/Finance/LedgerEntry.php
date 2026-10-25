<?php

namespace App\Services\Finance;

use App\Enums\FinanceChannel;
use Carbon\CarbonImmutable;

/**
 * One movement of money on a booking: a verified payment, what staff took at the handover or
 * the return, or a refund (negative). KES minor units.
 */
final readonly class LedgerEntry
{
    public const KIND_PAYMENT = 'payment';

    public const KIND_HANDOVER = 'handover';

    public const KIND_RETURN = 'return';

    public const KIND_REFUND = 'refund';

    public function __construct(
        public CarbonImmutable $at,
        public string $kind,
        public int $amountMinor,
        public ?FinanceChannel $channel,
        public int $bookingId,
        public ?string $bookingReference,
        public ?int $carId,
        public ?string $carName,
        public ?string $reference,
    ) {}

    public function date(): string
    {
        return FinancePeriod::localDate($this->at);
    }

    public function isRefund(): bool
    {
        return $this->kind === self::KIND_REFUND;
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_PAYMENT => 'Reservation payment',
            self::KIND_HANDOVER => 'Balance at handover',
            self::KIND_RETURN => 'Charges at return',
            default => 'Refund',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'at' => $this->at->toIso8601String(),
            'date' => $this->date(),
            'kind' => $this->kind,
            'kind_label' => $this->kindLabel(),
            'amount_minor' => $this->amountMinor,
            'channel' => $this->channel?->value,
            'channel_label' => $this->channel?->label(),
            'booking_reference' => $this->bookingReference,
            'car' => $this->carName,
            'reference' => $this->reference,
        ];
    }
}
