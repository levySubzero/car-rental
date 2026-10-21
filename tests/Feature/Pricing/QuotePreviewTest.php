<?php

use App\Models\Car;
use App\Models\DeliveryZone;
use Database\Seeders\PricingSeeder;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    $this->car = Car::factory()->create(['daily_rate_minor' => 1_500_000, 'car_value_minor' => 800_000_000, 'is_published' => true]);
    $this->travelTo(now()->setDate(2026, 10, 25));
});

function previewPayload(array $overrides = []): array
{
    return [
        'car' => test()->car->slug,
        'pickup_at' => '2026-11-02T10:00:00+03:00',
        'return_at' => '2026-11-04T10:00:00+03:00',
        'extras' => ['chauffeur'],
        'delivery_zone_id' => DeliveryZone::firstWhere('name', 'Nairobi')->id,
        'driver_dob' => '1991-03-15',
        ...$overrides,
    ];
}

it('prices a booking for renters without exposing the car value', function () {
    $response = $this->postJson(route('quote.preview'), previewPayload())
        ->assertOk()
        ->assertJsonPath('days', 3)
        ->assertJsonPath('total.major', '66350.00')
        ->assertJsonPath('reservation.major', '16588.00')
        ->assertJsonPath('deposit.major', '160000.00')
        ->assertJsonPath('display.USD.total.major', '526.59')
        ->assertJsonPath('lines.1.code', 'chauffeur');

    expect($response->json())->not->toHaveKeys(['deposit_band', 'fx', 'settings', 'settings_hash'])
        ->and($response->content())->not->toContain('8000000');
});

it('reports an age outside every band on the date of birth field', function () {
    $this->postJson(route('quote.preview'), previewPayload(['driver_dob' => '2010-01-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('driver_dob');
});

it('validates the preview input', function (array $input, string $field) {
    $this->postJson(route('quote.preview'), previewPayload($input))->assertJsonValidationErrors($field);
})->with([
    'unpublished car' => [['car' => 'nope'], 'car'],
    'past pickup' => [['pickup_at' => '2026-10-01T10:00:00+03:00'], 'pickup_at'],
    'return before pickup' => [['return_at' => '2026-11-01T10:00:00+03:00'], 'return_at'],
    'unknown extra' => [['extras' => ['jetpack']], 'extras.0'],
    'listed-only charge' => [['extras' => ['valeting']], 'extras.0'],
    'inactive zone' => [['delivery_zone_id' => 999], 'delivery_zone_id'],
]);

it('refuses unpublished cars', function () {
    $this->car->forceFill(['is_published' => false])->save();

    $this->postJson(route('quote.preview'), previewPayload())->assertJsonValidationErrors('car');
});

it('is rate limited', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->postJson(route('quote.preview'), previewPayload())->assertOk();
    }

    $this->postJson(route('quote.preview'), previewPayload())->assertTooManyRequests();
});
