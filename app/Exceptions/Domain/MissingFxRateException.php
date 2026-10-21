<?php

namespace App\Exceptions\Domain;

class MissingFxRateException extends DomainException
{
    public function __construct(public readonly string $currency)
    {
        parent::__construct("There is no exchange rate for {$currency}. Set one on the exchange rates page.");
    }
}
