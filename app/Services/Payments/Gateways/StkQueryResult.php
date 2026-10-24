<?php

namespace App\Services\Payments\Gateways;

/**
 * Where an STK push stands according to Daraja's query API. The query carries no receipt or
 * amount: it only confirms the outcome a callback reported, or settles one whose callback
 * never came. ResultCode 0 is paid; 1032 (cancelled), 1037 (no answer from the phone), 1
 * (insufficient funds), 2001 (wrong PIN) and the rest are failures.
 */
final readonly class StkQueryResult
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    /**
     * @param  self::PENDING|self::PAID|self::FAILED  $state
     */
    public function __construct(
        public string $state,
        public ?string $resultCode,
        public string $description,
    ) {}

    public static function pending(string $description = 'The transaction is being processed'): self
    {
        return new self(self::PENDING, null, $description);
    }

    public static function fromResultCode(string $code, string $description): self
    {
        return new self($code === '0' ? self::PAID : self::FAILED, $code, $description);
    }

    public function isPending(): bool
    {
        return $this->state === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->state === self::PAID;
    }

    public function isFailed(): bool
    {
        return $this->state === self::FAILED;
    }
}
