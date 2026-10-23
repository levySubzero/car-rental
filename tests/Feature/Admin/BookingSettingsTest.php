<?php

use App\Settings\BookingSettings;
use Inertia\Testing\AssertableInertia as Assert;

function bookingSettingsPayload(array $overrides = []): array
{
    $resident = ['documents' => ['id_front', 'id_back', 'licence'], 'kra_pin' => 'required'];
    $passport = ['documents' => ['passport', 'licence'], 'kra_pin' => 'hidden'];

    return array_replace([
        'min_licence_years' => '2',
        'licence_must_outlive_return' => true,
        'flight_notice_days' => '3',
        'document_requirements' => [
            'national_id' => ['resident' => $resident, 'non_resident' => $resident],
            'passport' => ['resident' => $passport, 'non_resident' => $passport],
        ],
    ], $overrides);
}

it('shows the booking tab with its options', function () {
    $this->actingAs(staffWith(['settings.booking.update']))
        ->get(route('admin.settings.edit', 'booking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/edit')
            ->where('values.min_licence_years', 3)
            ->has('context.documents', 5)
            ->has('context.idTypes', 2)
            ->has('context.kraPinOptions', 3));
});

it('forbids staff without the permission', function () {
    $this->actingAs(staffWith(['settings.pricing.update']))
        ->put(route('admin.settings.update', 'booking'), bookingSettingsPayload())
        ->assertForbidden();
});

it('saves the licence rules and the document matrix, in a fixed order', function () {
    $payload = bookingSettingsPayload();
    $payload['document_requirements']['national_id']['resident']['documents'] = ['licence', 'id_front'];

    $this->actingAs(staffWith(['settings.booking.update']))
        ->put(route('admin.settings.update', 'booking'), $payload)
        ->assertSessionHasNoErrors();

    $settings = app(BookingSettings::class);

    expect($settings->min_licence_years)->toBe(2)
        ->and($settings->flight_notice_days)->toBe(3)
        ->and($settings->document_requirements['national_id']['resident'])->toBe(['documents' => ['id_front', 'licence'], 'kra_pin' => 'required'])
        ->and($settings->document_requirements['passport']['non_resident']['kra_pin'])->toBe('hidden')
        // Not on this tab (D37).
        ->and($settings->max_additional_drivers)->toBe(1);
});

it('validates the matrix', function () {
    $payload = bookingSettingsPayload(['min_licence_years' => '99']);
    $payload['document_requirements']['passport']['resident'] = ['documents' => ['selfie'], 'kra_pin' => 'maybe'];

    $this->actingAs(staffWith(['settings.booking.update']))
        ->put(route('admin.settings.update', 'booking'), $payload)
        ->assertSessionHasErrors([
            'min_licence_years',
            'document_requirements.passport.resident.documents.0',
            'document_requirements.passport.resident.kra_pin',
        ]);
});
