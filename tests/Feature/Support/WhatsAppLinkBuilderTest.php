<?php

use App\Settings\GeneralSettings;
use App\Support\WhatsAppLinkBuilder;

// The same cases are in resources/js/lib/__tests__/whatsapp.test.ts: both sides must agree.
it('fills templates and drops placeholders without a value', function (string $template, array $values, string $expected) {
    expect(WhatsAppLinkBuilder::fill($template, $values))->toBe($expected);
})->with([
    'all values' => ['Rent the {car} for {dates}.', ['car' => 'Mazda Demio', 'dates' => '3–5 Nov'], 'Rent the Mazda Demio for 3–5 Nov.'],
    'no dates' => ['Rent the {car} for {dates}.', ['car' => 'Mazda Demio'], 'Rent the Mazda Demio.'],
    'blank value' => ['About booking {reference} on {dates}, thanks', ['reference' => 'BX-1', 'dates' => '  '], 'About booking BX-1, thanks'],
    'no placeholders' => ['Hello there.', [], 'Hello there.'],
]);

it('builds a wa.me link from the settings', function () {
    $settings = app(GeneralSettings::class);
    $settings->whatsapp_number = '+254 724 422 051';
    $settings->whatsapp_templates = [...$settings->whatsapp_templates, 'car_enquiry' => "Hi, I'm interested in the {car} for {dates}."];
    $settings->save();

    expect(app(WhatsAppLinkBuilder::class)->forCar('Mazda Demio'))
        ->toBe('https://wa.me/254724422051?text=Hi%2C%20I%27m%20interested%20in%20the%20Mazda%20Demio.');
});

it('has no link until a WhatsApp number is set', function () {
    $settings = app(GeneralSettings::class);
    $settings->whatsapp_number = null;
    $settings->save();

    expect(app(WhatsAppLinkBuilder::class)->general())->toBeNull();
});
