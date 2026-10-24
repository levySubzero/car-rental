<?php

namespace App\Enums;

/**
 * The payment methods the booking flow knows (D25). Rows are seeded; staff edit their
 * instructions and flags but cannot add or remove methods.
 */
enum PaymentMethodCode: string
{
    case Mpesa = 'mpesa';
    case Bank = 'bank';
    case Card = 'card';
    case Cash = 'cash';

    /**
     * Can be paid through a gateway from the booking page (M11B): M-Pesa by Daraja, card by Paystack.
     */
    public function supportsOnline(): bool
    {
        return $this === self::Mpesa || $this === self::Card;
    }
}
