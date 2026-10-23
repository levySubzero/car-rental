<?php

namespace App\Http\Requests\Public\Booking;

use App\Enums\BookingDocument;
use App\Enums\IdType;
use App\Http\Middleware\ResolveBookingToken;
use App\Models\Booking;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 2, the main driver and their documents (M8.11, M8.12). Identity numbers and the KRA
 * PIN are never sent back to the browser, so once saved they may be left blank to keep them. Documents are media
 * uuids uploaded with the booking token; which ones are required is checked after saving
 * (DriverEligibilityService), so the renter's progress is kept.
 */
class SaveDriverStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $booking = $this->booking();
        $saved = $booking->driver !== null;
        $keepable = $saved ? 'nullable' : 'required';

        $rules = [
            'first_name' => ['required', 'string', 'max:80'],
            'second_name' => ['nullable', 'string', 'max:80'],
            'surname' => ['required', 'string', 'max:80'],
            'dob' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'nationality' => ['required', 'string', 'max:80'],
            'is_kenyan_resident' => ['required', 'boolean'],
            'id_type' => ['required', Rule::enum(IdType::class)],
            'id_number' => [$keepable, 'string', 'max:30'],
            'kra_pin' => ['nullable', 'string', 'regex:/^[A-Za-z][0-9]{9}[A-Za-z]$/'],
            'licence_number' => [$keepable, 'string', 'max:30'],
            'licence_expiry' => ['required', 'date', 'after:today'],
            'licence_years_declared' => ['required', 'integer', 'between:0,70'],
            'documents' => ['array'],
        ];

        foreach (BookingDocument::cases() as $document) {
            $rules["documents.{$document->value}"] = ['nullable', 'uuid', new PendingOrOwnedMedia($document->collection(), $booking, $booking)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number with digits only, for example +254 724 422 051.',
            'kra_pin.regex' => 'A KRA PIN is a letter, nine digits and a letter, for example A123456789Z.',
            'licence_expiry.after' => 'Your licence has expired.',
        ];
    }

    public function booking(): Booking
    {
        return ResolveBookingToken::booking($this);
    }

    /**
     * The driver's details; blank identity numbers are left out so the saved ones are kept.
     *
     * @return array<string, mixed>
     */
    public function driverData(): array
    {
        $data = $this->safe()->except(['documents']);
        $data['is_kenyan_resident'] = $this->boolean('is_kenyan_resident');
        $data['licence_years_declared'] = $this->integer('licence_years_declared');

        if ($this->filled('kra_pin')) {
            $data['kra_pin'] = strtoupper((string) $data['kra_pin']);
        }

        foreach (['id_number', 'licence_number', 'kra_pin'] as $field) {
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * The uploaded file for each document, by BookingDocument value (null: none).
     *
     * @return array<string, string|null>
     */
    public function documents(): array
    {
        $documents = [];

        foreach (BookingDocument::cases() as $document) {
            $uuid = $this->validated("documents.{$document->value}");
            $documents[$document->value] = is_string($uuid) && $uuid !== '' ? $uuid : null;
        }

        return $documents;
    }
}
