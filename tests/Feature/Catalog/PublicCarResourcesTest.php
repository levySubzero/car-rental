<?php

use App\Http\Resources\Public\CarCardResource;
use App\Http\Resources\Public\CarDetailResource;
use App\Models\Car;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Settings\PricingSettings;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();

    $staff = User::factory()->staff()->create();
    $this->car = Car::factory()->published()->withValue()->create([
        'metadata' => ['secret_note' => 'bought at auction'],
        'damage_excess_minor' => 100_000_00,
        'damage_excess_currency' => 'USD',
    ]);
    app(MediaService::class)->sync($this->car, 'car.gallery', [CatalogFixtures::photo($staff)->uuid]);
    $this->car->load(['brand.logo', 'carType', 'cover', 'gallery']);
});

it('never exposes the car value or the extra info', function (string $resource) {
    $json = json_encode($resource::make($this->car)->resolve());

    expect($json)->not->toContain('car_value')
        ->not->toContain('450000000')
        ->not->toContain('metadata')
        ->not->toContain('bought at auction');
})->with(['card' => [CarCardResource::class], 'detail' => [CarDetailResource::class]]);

it('shows the card essentials with the site mileage default', function () {
    app(PricingSettings::class)->fill(['default_mileage_km' => 250])->save();

    $card = CarCardResource::make($this->car)->resolve();

    expect($card)->toHaveKeys(['slug', 'name', 'brand', 'car_type', 'cover', 'daily_rate', 'transmission', 'seats', 'doors'])
        ->and($card['mileage_limit_km'])->toBe(250)
        ->and($card['brand']->resolve()['slug'])->toBe($this->car->brand->slug)
        ->and($card['cover'])->not->toBeNull();
});

it('adds the gallery and damage excess on the detail page', function () {
    $detail = json_decode(json_encode(CarDetailResource::make($this->car)->resolve()), true);

    expect($detail['gallery'])->toHaveCount(1)
        ->and($detail['damage_excess']['display'])->toBe('USD 100,000');
});

it('uses the car\'s own mileage limit when it has one', function () {
    $this->car->update(['mileage_limit_km' => 500]);

    expect(CarCardResource::make($this->car)->resolve()['mileage_limit_km'])->toBe(500);
});
