<?php

use App\Enums\Transmission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use App\Services\Media\MediaService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->staff = User::factory()->staff()->create();
    $this->toyota = Brand::factory()->create(['name' => 'Toyota', 'slug' => 'toyota']);
    $this->bmw = Brand::factory()->create(['name' => 'BMW', 'slug' => 'bmw']);
    $this->suv = CarType::factory()->create(['name' => 'SUVs', 'slug' => 'suvs']);
    $this->sedan = CarType::factory()->create(['name' => 'Executive Sedans', 'slug' => 'executive-sedans']);
});

function fleetCar(User $staff, array $attributes = []): Car
{
    $car = Car::factory()->published()->create($attributes);
    app(MediaService::class)->sync($car, 'car.gallery', [CatalogFixtures::photo($staff)->uuid]);

    return $car;
}

it('lists published cars only, featured first', function () {
    fleetCar($this->staff, ['name' => 'Plain', 'brand_id' => $this->toyota->id]);
    fleetCar($this->staff, ['name' => 'Star', 'brand_id' => $this->bmw->id, 'is_featured' => true]);
    Car::factory()->create(['name' => 'Draft']);

    $this->get('/fleet')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/fleet')
            ->where('heading', 'Our fleet')
            ->where('preset', null)
            ->has('cars.data', 2)
            ->where('cars.data.0.name', 'Star')
            ->where('cars.meta.total', 2)
            ->missing('cars.data.0.car_value_minor')
            ->where('filters.sort', 'recommended')
            ->has('options.brands', 2));
});

it('filters by brand, type, gearbox and seats', function () {
    fleetCar($this->staff, ['name' => 'Toyota SUV', 'brand_id' => $this->toyota->id, 'car_type_id' => $this->suv->id, 'transmission' => Transmission::Automatic, 'seats' => 7]);
    fleetCar($this->staff, ['name' => 'Toyota Sedan', 'brand_id' => $this->toyota->id, 'car_type_id' => $this->sedan->id, 'transmission' => Transmission::Manual, 'seats' => 5]);
    fleetCar($this->staff, ['name' => 'BMW SUV', 'brand_id' => $this->bmw->id, 'car_type_id' => $this->suv->id, 'transmission' => Transmission::Automatic, 'seats' => 5]);

    $names = fn (string $query) => collect($this->get("/fleet?{$query}")->inertiaProps('cars.data'))->pluck('name')->sort()->values()->all();

    expect($names('brand=toyota'))->toBe(['Toyota SUV', 'Toyota Sedan'])
        ->and($names('type=suvs'))->toBe(['BMW SUV', 'Toyota SUV'])
        ->and($names('transmission=manual'))->toBe(['Toyota Sedan'])
        ->and($names('seats=7'))->toBe(['Toyota SUV'])
        ->and($names('brand=toyota&type=suvs&seats=5'))->toBe(['Toyota SUV']);
});

it('sorts by price both ways', function () {
    fleetCar($this->staff, ['name' => 'Mid', 'daily_rate_minor' => 10_000_00]);
    fleetCar($this->staff, ['name' => 'Cheap', 'daily_rate_minor' => 4_500_00]);
    fleetCar($this->staff, ['name' => 'Dear', 'daily_rate_minor' => 40_000_00]);

    $order = fn (string $sort) => collect($this->get("/fleet?sort={$sort}")->inertiaProps('cars.data'))->pluck('name')->all();

    expect($order('price_asc'))->toBe(['Cheap', 'Mid', 'Dear'])
        ->and($order('price_desc'))->toBe(['Dear', 'Mid', 'Cheap']);
});

it('ignores filters it does not recognise, including a hidden car type', function () {
    $this->suv->update(['is_active' => false]);
    fleetCar($this->staff, ['car_type_id' => $this->suv->id]);
    fleetCar($this->staff, ['car_type_id' => $this->sedan->id]);

    $this->get('/fleet?type=suvs&brand=nope&transmission=hover&seats=99&sort=random&page=x')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['brand' => null, 'type' => null, 'transmission' => null, 'seats' => null, 'sort' => 'recommended'])
            // Hiding a type never hides its cars from the fleet.
            ->has('cars.data', 2)
            ->has('options.types', 1));
});

it('pages the fleet twelve at a time and keeps the filters in the links', function () {
    foreach (range(1, 14) as $i) {
        fleetCar($this->staff, ['transmission' => Transmission::Automatic]);
    }

    $this->get('/fleet?transmission=automatic&page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('cars.data', 2)
            ->where('cars.meta.current_page', 2)
            ->where('cars.links.prev', fn (string $url) => str_contains($url, 'transmission=automatic')));
});

it('shows a brand page and a car type page as filtered fleets', function () {
    fleetCar($this->staff, ['name' => 'Land Cruiser', 'brand_id' => $this->toyota->id, 'car_type_id' => $this->suv->id]);
    fleetCar($this->staff, ['name' => 'X5', 'brand_id' => $this->bmw->id, 'car_type_id' => $this->suv->id]);

    $this->get('/car-brands/toyota')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/fleet')
            ->where('heading', 'Toyota')
            ->where('preset', ['kind' => 'brand', 'slug' => 'toyota'])
            ->where('filters.brand', 'toyota')
            ->has('cars.data', 1));

    $this->get('/car-types/suvs?brand=bmw')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('heading', 'SUVs')
            ->where('preset.kind', 'type')
            ->has('cars.data', 1)
            ->where('cars.data.0.name', 'X5'));
});

it('has no page for an inactive brand or a hidden or unknown car type', function () {
    $this->bmw->update(['is_active' => false]);
    $this->suv->update(['is_active' => false]);

    $this->get('/car-brands/bmw')->assertNotFound();
    $this->get('/car-brands/nope')->assertNotFound();
    $this->get('/car-types/suvs')->assertNotFound();
    $this->get('/car-types/nope')->assertNotFound();
});
