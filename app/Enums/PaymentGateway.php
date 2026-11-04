<?php

namespace App\Enums;

/**
 * Where an online payment came from (M11B, D42): an STK push the renter approved on their
 * phone, a paybill payment they made themselves (C2B), a Paystack card payment, or M-Pesa taken
 * through Paystack's window (M12B.16).
 */
enum PaymentGateway: string
{
    case MpesaStk = 'mpesa_stk';
    case MpesaC2b = 'mpesa_c2b';
    case Paystack = 'paystack';
    case PaystackMpesa = 'paystack_mpesa';

    /**
     * @return list<self>
     */
    public static function paystackCases(): array
    {
        return [self::Paystack, self::PaystackMpesa];
    }

    public function isPaystack(): bool
    {
        return in_array($this, self::paystackCases(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::MpesaStk => 'M-Pesa STK',
            self::MpesaC2b => 'M-Pesa paybill',
            self::Paystack => 'Paystack',
            self::PaystackMpesa => 'Paystack M-Pesa',
        };
    }

    public function method(): PaymentMethodCode
    {
        return match ($this) {
            self::MpesaStk, self::MpesaC2b, self::PaystackMpesa => PaymentMethodCode::Mpesa,
            self::Paystack => PaymentMethodCode::Card,
        };
    }
}
