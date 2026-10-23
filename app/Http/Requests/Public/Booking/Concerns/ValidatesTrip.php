<?php

namespace App\Http\Requests\Public\Booking\Concerns;

use App\Enums\HandoverMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Step 1, trip and extras (M8.10, M8.21). Times are ISO instants. Each leg is at the office
 * or at an address in the chosen delivery zone (D33); the address is free text.
 */
trait ValidatesTrip
{
    private const PHONE = 'regex:/^\+?[0-9 ()-]{7,30}$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function tripRules(): array
    {
        $rules = [
            'pickup_at' => ['required', 'date', 'after:now'],
            'return_at' => ['required', 'date', 'after:pickup_at', 'before:+1 year'],
            'pickup_mode' => ['required', Rule::enum(HandoverMode::class)],
            'return_mode' => ['required', Rule::enum(HandoverMode::class)],
            'delivery_zone_id' => [
                Rule::requiredIf(fn () => $this->usesAddress('pickup_mode') || $this->usesAddress('return_mode')),
                'nullable', 'integer', Rule::exists('delivery_zones', 'id')->where('is_active', true),
            ],
            'extras' => ['array', 'max:10'],
            // Codes of active charges offered as extras (D32).
            'extras.*' => ['string', 'distinct', Rule::exists('charges', 'code')->where('is_active', true)->where('applies', 'extra')],
            'flight_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 -]+$/'],
        ];

        foreach (['delivery' => 'pickup_mode', 'return' => 'return_mode'] as $leg => $mode) {
            $required = Rule::requiredIf(fn () => $this->usesAddress($mode));
            $rules["{$leg}_address"] = [$required, 'nullable', 'string', 'max:500'];
            $rules["{$leg}_landmark"] = ['nullable', 'string', 'max:255'];
            $rules["{$leg}_lat"] = ['nullable', 'numeric', 'between:-90,90', "required_with:{$leg}_lng"];
            $rules["{$leg}_lng"] = ['nullable', 'numeric', 'between:-180,180', "required_with:{$leg}_lat"];
            $rules["{$leg}_contact_phone"] = [$required, 'nullable', 'string', 'max:30', self::PHONE];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function tripMessages(): array
    {
        return [
            'pickup_at.after' => 'The pickup must be in the future.',
            'return_at.after' => 'The return must be after the pickup.',
            'return_at.before' => 'Bookings can be made up to a year ahead.',
            'delivery_zone_id.required' => 'Choose the area for delivery or collection.',
            'delivery_address.required' => 'Tell us where to deliver the car.',
            'return_address.required' => 'Tell us where to collect the car.',
            'delivery_contact_phone.required' => 'Give a phone number for the driver.',
            'return_contact_phone.required' => 'Give a phone number for the driver.',
            'delivery_contact_phone.regex' => 'Enter a phone number with digits only, for example +254 724 422 051.',
            'return_contact_phone.regex' => 'Enter a phone number with digits only, for example +254 724 422 051.',
        ];
    }

    /**
     * The validated trip, with the address fields of an office leg cleared.
     *
     * @return array<string, mixed>
     */
    public function tripData(): array
    {
        $data = [
            // Stored in UTC; the renter sends Nairobi times with their offset.
            'pickup_at' => $this->date('pickup_at')?->utc(),
            'return_at' => $this->date('return_at')?->utc(),
            'pickup_mode' => $this->enum('pickup_mode', HandoverMode::class),
            'return_mode' => $this->enum('return_mode', HandoverMode::class),
            'extras' => array_values(array_map('strval', (array) $this->validated('extras', []))),
            'flight_number' => $this->filled('flight_number') ? strtoupper(trim((string) $this->validated('flight_number'))) : null,
        ];

        $usesZone = $this->usesAddress('pickup_mode') || $this->usesAddress('return_mode');
        $data['delivery_zone_id'] = $usesZone ? (int) $this->validated('delivery_zone_id') : null;

        foreach (['delivery' => 'pickup_mode', 'return' => 'return_mode'] as $leg => $mode) {
            foreach (['address', 'landmark', 'lat', 'lng', 'contact_phone'] as $field) {
                $data["{$leg}_{$field}"] = $this->usesAddress($mode) ? $this->validated("{$leg}_{$field}") : null;
            }
        }

        return $data;
    }

    private function usesAddress(string $mode): bool
    {
        return $this->input($mode) === HandoverMode::Address->value;
    }
}
