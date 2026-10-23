<?php

namespace App\Services\Booking;

use App\Enums\BookingDocument;
use App\Enums\IdType;
use App\Enums\KraPinRule;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Settings\BookingSettings;
use Carbon\CarbonImmutable;

/**
 * Whether the main driver may rent (TECHNICAL_PLAN §11.4): licence rules and the documents
 * required for their id type and residency. The age limits come from the deposit bands and
 * are checked by the quote (QuoteException::driverAge).
 */
class DriverEligibilityService
{
    public function __construct(private readonly BookingSettings $settings) {}

    /**
     * @return array{documents: list<BookingDocument>, kra_pin: KraPinRule}
     */
    public function requirementsFor(IdType $idType, bool $resident): array
    {
        $rule = $this->settings->document_requirements[$idType->value][$resident ? 'resident' : 'non_resident'] ?? null;

        return [
            'documents' => array_values(array_filter(array_map(
                fn (string $document) => BookingDocument::tryFrom($document),
                $rule['documents'] ?? [],
            ))),
            'kra_pin' => KraPinRule::tryFrom($rule['kra_pin'] ?? '') ?? KraPinRule::Optional,
        ];
    }

    /**
     * Field errors that stop the booking, keyed by the driver form's input names.
     *
     * @return array<string, string>
     */
    public function check(Booking $booking, BookingDriver $driver): array
    {
        $errors = [];
        // Calendar dates in the business time zone (D30): a licence ending on the return day has run out.
        $returnDate = $booking->return_at->setTimezone((string) config('baines.timezone'))->toDateString();

        if ($this->settings->licence_must_outlive_return && $driver->licence_expiry->toDateString() <= $returnDate) {
            $errors['licence_expiry'] = 'Your licence must still be valid after the return date.';
        }

        if ($driver->licence_years_declared < $this->settings->min_licence_years) {
            $errors['licence_years_declared'] = "You need to have held a licence for at least {$this->settings->min_licence_years} years.";
        }

        $requirements = $this->requirementsFor($driver->id_type, $driver->is_kenyan_resident);

        if ($requirements['kra_pin'] === KraPinRule::Required && blank($driver->kra_pin)) {
            $errors['kra_pin'] = 'Please give your KRA PIN.';
        }

        foreach ($requirements['documents'] as $document) {
            if (! $booking->mediaIn($document->collection())->exists()) {
                $errors["documents.{$document->value}"] = "Please upload your {$document->label()}.";
            }
        }

        return $errors;
    }

    /**
     * Things the renter should know that don't stop the booking.
     *
     * @return list<string>
     */
    public function warnings(Booking $booking): array
    {
        $warnings = [];
        $days = $this->settings->flight_notice_days;

        if (filled($booking->flight_number) && $booking->pickup_at->lessThan(CarbonImmutable::now()->addDays($days))) {
            $warnings[] = "For airport pickups we ask for {$days} days' notice. We will confirm by phone that the car can meet your flight.";
        }

        return $warnings;
    }
}
