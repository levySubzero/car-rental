<?php

namespace App\Enums;

/**
 * Which gateway takes M-Pesa on the booking page (M12B.16, D46). Renters see one M-Pesa option
 * either way; attempts already started finish on the gateway they began with.
 */
enum MpesaProvider: string
{
    case Daraja = 'daraja';
    case Paystack = 'paystack';

    public static function current(): self
    {
        return self::tryFrom((string) config('services.mpesa.provider')) ?? self::Daraja;
    }

    public function label(): string
    {
        return match ($this) {
            self::Daraja => 'Daraja (Safaricom)',
            self::Paystack => 'Paystack',
        };
    }
}
