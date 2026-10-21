<?php

namespace App\Exceptions\Domain;

/**
 * A quote cannot be made from this input. `field` names the input to show the message on.
 */
class QuoteException extends DomainException
{
    private function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function returnBeforePickup(): self
    {
        return new self('The return must be after the pickup.', 'return_at');
    }

    /**
     * No active deposit band covers the driver's age at pickup (PRD Q2).
     */
    public static function driverAge(int $age): self
    {
        return new self("Drivers must be within the allowed age range; this driver is {$age} at pickup.", 'driver_dob');
    }

    public static function unknownZone(): self
    {
        return new self('That delivery zone is not available.', 'delivery_zone_id');
    }

    protected function errorField(): ?string
    {
        return $this->field;
    }
}
