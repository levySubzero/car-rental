<?php

use App\Enums\FxRateSource;
use App\Enums\Permission;
use App\Models\FxRate;
use App\Services\Pricing\FxRateService;
use App\Settings\FxSettings;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PricingFixtures;

beforeEach(fn () => PricingFixtures::currencies());

it('shows the exchange rate settings tab', function () {
    $this->actingAs(staffWith([Permission::SettingsFxUpdate]))
        ->get(route('admin.settings.edit', 'fx'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.margin_minor', 300)
            ->where('context.hasApiKey', false)
            ->has('context.providers', 2));
});

it('saves the exchange rate settings with the offset in KES', function () {
    $this->actingAs(staffWith([Permission::SettingsFxUpdate]))
        ->put(route('admin.settings.update', 'fx'), [
            'provider' => 'open_exchange_rates',
            'refresh_minutes' => 30,
            'margin_mode' => 'below',
            'margin_amount' => '2.5',
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
            'margin_mode' => 'below',
            'margin_amount' => '3.333',
            'stale_after_hours' => 0,
            'max_jump_percent' => 101,
        ])
        ->assertSessionHasErrors(['provider', 'refresh_minutes', 'margin_amount', 'stale_after_hours', 'max_jump_percent']);
});

function saveOffset(string $mode, ?string $amount = null)
{
    return test()->actingAs(staffWith([Permission::SettingsFxUpdate]))
        ->put(route('admin.settings.update', 'fx'), [
            'provider' => 'manual',
            'refresh_minutes' => 30,
            'margin_mode' => $mode,
            'margin_amount' => $amount,
            'stale_after_hours' => 12,
            'max_jump_percent' => 5,
        ]);
}

it('stores the offset signed and applies it to the display rate only', function (string $mode, ?string $amount, int $minor, string $effective) {
    FxRate::factory()->create(['currency_code' => 'USD', 'market_rate' => '129', 'source' => FxRateSource::Api, 'fetched_at' => now()]);

    saveOffset($mode, $amount)->assertSessionHasNoErrors();

    $table = app(FxRateService::class)->table();

    expect(app(FxSettings::class)->margin_minor)->toBe($minor)
        ->and($table->get('USD')->effective())->toBe($effective)
        // A foreign charge such as the chauffeur stays at the market rate.
        ->and($table->toBase(new Money(5000, 'USD'))->minor)->toBe(645000);
})->with([
    'below' => ['below', '2', 200, '127.000000'],
    'above' => ['above', '2', -200, '131.000000'],
    'exact' => ['exact', null, 0, '129.000000'],
]);

it('needs an amount unless the rate is exact', function () {
    saveOffset('below')->assertSessionHasErrors('margin_amount');
});

it('refuses an offset that would make a rate zero or negative', function () {
    FxRate::factory()->create(['currency_code' => 'USD', 'market_rate' => '129', 'source' => FxRateSource::Api, 'fetched_at' => now()]);

    saveOffset('below', '129')->assertSessionHasErrors('margin_amount');

    expect(app(FxSettings::class)->margin_minor)->toBe(300);
});
