<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\BookingDocument;
use App\Enums\IdType;
use App\Enums\KraPinRule;
use App\Enums\SettingsGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Licence rules, flight notice and the document matrix (M8.8). The additional-driver and
 * airport settings are not edited here: v1 books the main driver only, at the office or an
 * address (D37).
 */
class UpdateBookingSettingsRequest extends UpdateSettingsRequest
{
    private const RESIDENCIES = ['resident', 'non_resident'];

    public function group(): SettingsGroup
    {
        return SettingsGroup::Booking;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'min_licence_years' => ['required', 'integer', 'between:0,30'],
            'licence_must_outlive_return' => ['required', 'boolean'],
            'flight_notice_days' => ['required', 'integer', 'between:0,30'],
            'document_requirements' => ['required', 'array'],
        ];

        foreach (IdType::cases() as $idType) {
            foreach (self::RESIDENCIES as $residency) {
                $key = "document_requirements.{$idType->value}.{$residency}";
                $rules["{$key}.documents"] = ['present', 'array'];
                $rules["{$key}.documents.*"] = ['distinct', Rule::enum(BookingDocument::class)];
                $rules["{$key}.kra_pin"] = ['required', Rule::enum(KraPinRule::class)];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $matrix = [];

        // Rebuilt from the enums, so unknown keys never reach the settings.
        foreach (IdType::cases() as $idType) {
            foreach (self::RESIDENCIES as $residency) {
                /** @var list<string> $documents */
                $documents = $this->input("document_requirements.{$idType->value}.{$residency}.documents", []);

                $matrix[$idType->value][$residency] = [
                    'documents' => array_values(array_filter(
                        array_map(fn (BookingDocument $document) => $document->value, BookingDocument::cases()),
                        fn (string $document) => in_array($document, $documents, true),
                    )),
                    'kra_pin' => (string) $this->input("document_requirements.{$idType->value}.{$residency}.kra_pin"),
                ];
            }
        }

        return [
            'min_licence_years' => $this->integer('min_licence_years'),
            'licence_must_outlive_return' => $this->boolean('licence_must_outlive_return'),
            'flight_notice_days' => $this->integer('flight_notice_days'),
            'document_requirements' => $matrix,
        ];
    }
}
