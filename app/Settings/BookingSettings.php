<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Booking eligibility and document rules (TECHNICAL_PLAN §9, §11.4).
 */
class BookingSettings extends Settings
{
    public int $min_licence_years;

    public bool $licence_must_outlive_return;

    public bool $require_flight_number_for_airport;

    public int $max_additional_drivers;

    public int $flight_notice_days;

    /**
     * Required documents per id type and residency (PRD Q6).
     *
     * @phpstan-var array<string, array<string, array{documents: list<string>, kra_pin: 'required'|'optional'|'hidden'}>>
     */
    public array $document_requirements;

    // Payment method codes accepted for the deposit at pickup; empty means all active methods
    /** @var list<string> */
    public array $deposit_payment_methods;

    public static function group(): string
    {
        return 'booking';
    }
}
