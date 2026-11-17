<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethodCode;
use App\Enums\RoundingMode;
use App\Settings\PricingSettings;
use App\Support\Money;

/**
 * Transaction costs, which the client always pays (2026-11): added on top of an online
 * payment, and taken off a refund sent back by the same method. A share of the amount by
 * method (PricingSettings), rounded up to the shilling. Bank and cash carry none by default.
 */
class TransactionFees
{
    public function __construct(private readonly PricingSettings $pricing) {}

    public function rateBp(?PaymentMethodCode $method): int
    {
        return match ($method) {
            PaymentMethodCode::Mpesa => $this->pricing->mpesa_fee_bp,
            PaymentMethodCode::Card => $this->pricing->card_fee_bp,
            default => 0,
        };
    }

    public function on(Money $amount, ?PaymentMethodCode $method): Money
    {
        $rate = $this->rateBp($method);

        return $rate <= 0 || $amount->minor <= 0 ? Money::zero() : $amount->percentBp($rate, RoundingMode::CeilMajor);
    }
}
