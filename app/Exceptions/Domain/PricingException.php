<?php

namespace App\Exceptions\Domain;

class PricingException extends DomainException
{
    public static function baseCurrencyLocked(string $code): self
    {
        return new self("{$code} is the base currency: it cannot be deactivated or renamed.");
    }

    public static function currencyInUse(string $code, int $charges): self
    {
        return new self("{$code} is used by {$charges} active charge(s). Change or deactivate them first.");
    }

    public static function manualRateForBase(string $code): self
    {
        return new self("{$code} is the base currency, so it has no exchange rate.");
    }

    public static function inactiveCurrency(string $code): self
    {
        return new self("{$code} is not an active currency.");
    }
}
