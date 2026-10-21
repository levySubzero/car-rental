<?php

use App\Enums\DayCountingStrategy;
use App\Enums\Permission;
use App\Enums\RoundingMode;
use App\Settings\PricingSettings;
use Inertia\Testing\AssertableInertia as Assert;

function pricingPayload(array $overrides = []): array
{
    return [
        'reservation_percent' => '30',
        'reservation_rounding' => 'round',
        'day_counting' => 'daytime_blocks',
        'daytime_window_start' => '08:00',
        'daytime_window_end' => '20:00',
        'rolling_grace_minutes' => 30,
        'hold_minutes' => 90,
        'draft_expiry_hours' => 12,
        'turnaround_minutes' => 60,
        'default_mileage_km' => 250,
        'unlimited_mileage_after_days' => 7,
        ...$overrides,
    ];
}

it('shows the pricing settings tab with its options', function () {
    $this->actingAs(staffWith([Permission::SettingsPricingUpdate]))
        ->get(route('admin.settings.edit', 'pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.reservation_percent_bp', 2500)
            ->has('context.roundingOptions', 3)
            ->has('context.dayCountingOptions', 3));
});

it('saves the pricing settings with the percentage in basis points', function () {
    $this->actingAs(staffWith([Permission::SettingsPricingUpdate]))
        ->put(route('admin.settings.update', 'pricing'), pricingPayload())
        ->assertSessionHasNoErrors();

    $settings = app(PricingSettings::class);
    expect($settings->reservation_percent_bp)->toBe(3000)
        ->and($settings->reservation_rounding)->toBe(RoundingMode::Round)
        ->and($settings->day_counting)->toBe(DayCountingStrategy::DaytimeBlocks)
        ->and($settings->daytime_window_end)->toBe('20:00')
        ->and($settings->unlimited_mileage_after_days)->toBe(7)
        ->and($settings->base_currency)->toBe('KES');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'event' => 'updated']);
});

it('clears the unlimited mileage threshold', function () {
    $this->actingAs(staffWith([Permission::SettingsPricingUpdate]))
        ->put(route('admin.settings.update', 'pricing'), pricingPayload(['unlimited_mileage_after_days' => null]))
        ->assertSessionHasNoErrors();

    expect(app(PricingSettings::class)->unlimited_mileage_after_days)->toBeNull();
});

it('validates the pricing settings', function () {
    $this->actingAs(staffWith([Permission::SettingsPricingUpdate]))
        ->put(route('admin.settings.update', 'pricing'), pricingPayload([
            'reservation_percent' => '0',
            'reservation_rounding' => 'bankers',
            'day_counting' => 'weekly',
            'daytime_window_start' => '19:00',
            'daytime_window_end' => '07:00',
            'hold_minutes' => 1,
        ]))
        ->assertSessionHasErrors(['reservation_percent', 'reservation_rounding', 'day_counting', 'daytime_window_end', 'hold_minutes']);
});
