<?php

namespace App\Services\Payments\Gateways;

/**
 * A Paystack transaction as its verify endpoint reports it. Only `success` is money received;
 * `ongoing`, `pending` and `processing` may still turn into it, and anything else
 * (`failed`, `abandoned`, `reversed`) will not. `amountMinor` is in subunits, our minor units.
 */
final readonly class PaystackTransaction
{
    private const IN_FLIGHT = ['ongoing', 'pending', 'processing', 'queued'];

    public function __construct(
        public string $status,
        public string $reference,
        public int $amountMinor,
        public string $currency,
        public ?string $id,
        public ?string $channel,
        public ?string $gatewayResponse,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::IN_FLIGHT, true);
    }
}
