<?php

namespace App\Services\Pricing\Quote;

use App\Support\Money;

/**
 * One line of a quote. `amount` is in KES; `unit` is in the charge's own currency.
 * Lines that are not `counted` are shown to the renter but are not in the total:
 * counter charges, information-only charges and fees staff still have to quote.
 */
final readonly class QuoteLine
{
    public const KIND_RENTAL = 'rental';

    public const KIND_CHARGE = 'charge';

    public const KIND_DELIVERY = 'delivery';

    public function __construct(
        public string $kind,
        public string $code,
        public string $label,
        public int $quantity,
        public ?Money $unit,
        public ?Money $amount,
        public string $payableAt,
        public bool $counted,
        public ?string $note = null,
    ) {}

    /**
     * @return array{kind: string, code: string, label: string, quantity: int, unit: Money|null, amount: Money|null, payable_at: string, counted: bool, note: string|null}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'code' => $this->code,
            'label' => $this->label,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'amount' => $this->amount,
            'payable_at' => $this->payableAt,
            'counted' => $this->counted,
            'note' => $this->note,
        ];
    }
}
