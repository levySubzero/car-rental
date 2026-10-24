<?php

namespace App\Services\Payments\Gateways;

/**
 * Safaricom accepted the STK push and is prompting the phone. The result arrives later on
 * the callback, keyed by `checkoutRequestId`.
 */
final readonly class StkPushResult
{
    public function __construct(
        public string $merchantRequestId,
        public string $checkoutRequestId,
        public string $customerMessage,
    ) {}
}
