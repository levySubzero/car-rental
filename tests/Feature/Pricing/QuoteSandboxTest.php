<?php

use App\Enums\Permission;
use App\Models\Car;
use App\Models\DeliveryZone;
use Database\Seeders\PricingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    $this->car = Car::factory()->create(['name' => 'Toyota Prado TX', 'daily_rate_minor' => 1_500_000, 'car_value_minor' => 800_000_000]);
});

function sandboxQuery(array $overrides = []): array
{
    return [
        'car' => test()->car->slug,
        'pickup_at' => '2026-11-02T10:00',
        'return_at' => '2026-11-04T10:00',
        'extras' => ['chauffeur'],
        'delivery_zone_id' => (string) DeliveryZone::firstWhere('name', 'Nairobi')->id,
        'driver_dob' => '1991-03-15',
        ...$overrides,
    ];
}

it('shows an empty form until a car and both times are given', function () {
    $this->actingAs(staffWith([Permission::QuotesSandbox]))
        ->get(route('admin.quote-sandbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/quote-sandbox/index')
            ->where('quote', null)
            ->has('cars', 1)
            ->has('zones', 2)
            ->where('extras.0.value', 'chauffeur'));
});

it('reproduces the PRD worked example from the query string', function () {
    $this->actingAs(staffWith([Permission::QuotesSandbox, Permission::CarsViewValue]))
        ->get(route('admin.quote-sandbox', sandboxQuery()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.car', $this->car->slug)
            ->where('quote.days', 3)
            ->where('quote.total.major', '66350.00')
            ->where('quote.reservation.major', '16588.00')
            ->where('quote.balance.major', '49762.00')
            ->where('quote.deposit.major', '160000.00')
            ->where('quote.deposit_band.percent_bp', 200)
            ->where('quote.display.USD.total.major', '526.59')
            ->where('quote.settings.reservation_percent_bp', 2500));
});

it('hides the deposit from staff who cannot see car values', function () {
    $this->actingAs(staffWith([Permission::QuotesSandbox]))
        ->get(route('admin.quote-sandbox', sandboxQuery()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quote.total.major', '66350.00')
            ->where('quote.deposit', null)
            ->where('quote.deposit_band', null)
            ->where('quote.deposit_hidden', true)
            ->where('quote.display.USD.deposit', null));
});

it('shows why a quote cannot be made', function () {
    $this->actingAs(staffWith([Permission::QuotesSandbox]))
        ->get(route('admin.quote-sandbox', sandboxQuery(['driver_dob' => '2010-01-01'])))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('quote', null)->where('error', fn (string $error) => str_contains($error, 'age')));
});

it('validates the scenario', function () {
    $this->actingAs(staffWith([Permission::QuotesSandbox]))
        ->get(route('admin.quote-sandbox', sandboxQuery(['return_at' => '2026-11-01T10:00', 'extras' => ['jetpack']])))
        ->assertSessionHasErrors(['return_at', 'extras.0']);
});

it('needs quotes.sandbox', function () {
    $this->actingAs(staffWith([Permission::CarsView]))->get(route('admin.quote-sandbox'))->assertForbidden();
});
