<?php

use App\Enums\Permission;
use App\Settings\FxSettings;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the exchange rate settings tab', function () {
    $this->actingAs(staffWith([Permission::SettingsFxUpdate]))
        ->get(route('admin.settings.edit', 'fx'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.margin_minor', 300)
            ->where('context.hasApiKey', false)
            ->has('context.providers', 2));
});

it('saves the exchange rate settings with the margin in KES', function () {
    $this->actingAs(staffWith([Permission::SettingsFxUpdate]))
        ->put(route('admin.settings.update', 'fx'), [
            'provider' => 'open_exchange_rates',
            'refresh_minutes' => 30,
            'margin' => '2.5',
            'stale_after_hours' => 12,
            'max_jump_percent' => 5,
        ])
        ->assertSessionHasNoErrors();

    $settings = app(FxSettings::class);
    expect($settings->provider)->toBe('open_exchange_rates')
        ->and($settings->margin_minor)->toBe(250)
        ->and($settings->refresh_minutes)->toBe(30)
        ->and($settings->max_jump_percent)->toBe(5);
});

it('validates the exchange rate settings', function () {
    $this->actingAs(staffWith([Permission::SettingsFxUpdate]))
        ->put(route('admin.settings.update', 'fx'), [
            'provider' => 'exchangerate_api',
            'refresh_minutes' => 5,
            'margin' => '3.333',
            'stale_after_hours' => 0,
            'max_jump_percent' => 101,
        ])
        ->assertSessionHasErrors(['provider', 'refresh_minutes', 'margin', 'stale_after_hours', 'max_jump_percent']);
});
