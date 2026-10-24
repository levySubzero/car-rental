<?php

namespace App\Enums;

/**
 * Where an online payment came from (M11B, D42): an STK push the renter approved on their
 * phone, a paybill payment they made themselves (C2B), or a Paystack card payment.
 */
enum PaymentGateway: string
{
    case MpesaStk = 'mpesa_stk';
    case MpesaC2b = 'mpesa_c2b';
    case Paystack = 'paystack';

    public function label(): string
    {
        return match ($this) {
            self::MpesaStk => 'M-Pesa STK',
            self::MpesaC2b => 'M-Pesa paybill',
            self::Paystack => 'Paystack',
        };
    }

    public function method(): PaymentMethodCode
    {
        return match ($this) {
            self::MpesaStk, self::MpesaC2b => PaymentMethodCode::Mpesa,
            self::Paystack => PaymentMethodCode::Card,
        };
    }
}
