<?php

use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use App\Settings\RetentionSettings;
use App\Settings\SecuritySettings;
use Inertia\Testing\AssertableInertia as Assert;

function generalPayload(array $overrides = []): array
{
    return array_replace([
        'company_name' => 'Baines Executives Ltd',
        'legal_name' => null,
        'phones' => ['+254 700 000 000'],
        'whatsapp_number' => '+254700000000',
        'email' => 'hello@baines.test',
        'address' => 'Westlands, Nairobi',
        'map_embed_url' => null,
        'latitude' => '-1.2676',
        'longitude' => '36.8108',
        'socials' => ['instagram' => 'https://instagram.com/baines', 'facebook' => ''],
        'opening_hours' => [['days' => 'Mon – Sun', 'hours' => '07:00 – 21:00']],
        'whatsapp_templates' => [
            'car_enquiry' => 'Hi, I would like the {car} for {dates}.',
            'general_enquiry' => 'Hi.',
            'booking' => 'About {reference}.',
        ],
    ], $overrides);
}

it('redirects to the first tab the user may edit', function () {
    $this->actingAs(staffWith(['settings.retention.update']))
        ->get(route('admin.settings.index'))
        ->assertRedirect(route('admin.settings.edit', 'retention'));
});

it('shows only permitted tabs', function () {
    $this->actingAs(staffWith(['settings.general.update', 'settings.retention.update']))
        ->get(route('admin.settings.edit', 'general'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/edit')
            ->where('tabs', [['key' => 'general', 'label' => 'General'], ['key' => 'retention', 'label' => 'Data retention']])
            ->where('values.company_name', 'Baines Executives'));
});

it('forbids groups without permission', function () {
    $user = staffWith(['settings.retention.update']);

    $this->actingAs($user)->get(route('admin.settings.edit', 'general'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.settings.update', 'general'), generalPayload())->assertForbidden();
});

it('404s for groups that do not exist', function () {
    $this->actingAs(superAdmin())->get(route('admin.settings.edit', 'nope'))->assertNotFound();
});

it('saves general settings, drops empty socials and logs a diff', function () {
    $user = staffWith(['settings.general.update']);

    $this->actingAs($user)
        ->put(route('admin.settings.update', 'general'), generalPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $settings = app(GeneralSettings::class);

    expect($settings->company_name)->toBe('Baines Executives Ltd')
        ->and($settings->socials)->toBe(['instagram' => 'https://instagram.com/baines'])
        ->and($settings->latitude)->toBe(-1.2676);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'causer_id' => $user->id, 'description' => 'General settings updated']);
});

it('validates general settings', function () {
    $this->actingAs(staffWith(['settings.general.update']))
        ->put(route('admin.settings.update', 'general'), generalPayload([
            'company_name' => '',
            'phones' => ['call me'],
            'socials' => ['myspace' => 'https://myspace.com/x'],
            'whatsapp_templates' => ['car_enquiry' => ''],
        ]))
        ->assertSessionHasErrors(['company_name', 'phones.0', 'socials', 'whatsapp_templates.car_enquiry']);
});

it('saves retention settings as integers', function () {
    $this->actingAs(staffWith(['settings.retention.update']))
        ->put(route('admin.settings.update', 'retention'), [
            'kyc_retention_months' => '18',
            'draft_retention_days' => '7',
            'activity_log_retention_months' => '24',
        ])
        ->assertSessionHasNoErrors();

    expect(app(RetentionSettings::class)->kyc_retention_months)->toBe(18);
});

it('saves notification settings', function () {
    $channels = collect(['booking_received', 'staff_new_booking', 'payment_submitted', 'booking_confirmed', 'payment_rejected', 'delivery_updated'])
        ->mapWithKeys(fn ($event) => [$event => ['mail' => true, 'whatsapp' => false]])
        ->all();

    $this->actingAs(staffWith(['settings.notification.update']))
        ->put(route('admin.settings.update', 'notification'), [
            'staff_emails' => ['ops@baines.test'],
            'staff_whatsapp_number' => null,
            'channels' => $channels,
            'voucher_email_subject' => 'Booking {reference} confirmed',
            'voucher_email_intro' => 'Thanks.',
            'voucher_whatsapp_text' => 'Booking {reference} for the {car} is confirmed.',
        ])
        ->assertSessionHasNoErrors();

    expect(app(NotificationSettings::class)->voucher_whatsapp_text)->toBe('Booking {reference} for the {car} is confirmed.');
});

it('turns required 2FA on from the security tab', function () {
    $this->actingAs(staffWith(['settings.security.update']))
        ->put(route('admin.settings.update', 'security'), ['require_two_factor' => true])
        ->assertSessionHasNoErrors();

    expect(app(SecuritySettings::class)->require_two_factor)->toBeTrue();
    $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'description' => 'Security settings updated']);
});

it('gates settings routes with can: middleware', function () {
    $route = app('router')->getRoutes()->getByName('admin.settings.update');

    expect($route->gatherMiddleware())->toContain('can:update-settings,group');
});
