<?php

use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use App\Services\Media\MediaService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->staff = User::factory()->staff()->create();
});

function pagedCar(User $staff, array $attributes = [], int $photos = 1): Car
{
    $car = Car::factory()->published()->withValue()->create($attributes);
    $uuids = array_map(fn () => CatalogFixtures::photo($staff)->uuid, range(1, $photos));
    app(MediaService::class)->sync($car, 'car.gallery', $uuids, actor: $staff);

    return $car;
}

it('shows a published car with its photos, details and related cars', function () {
    $type = CarType::factory()->create();
    $car = pagedCar($this->staff, ['name' => 'Toyota Prado TX', 'car_type_id' => $type->id, 'damage_excess_minor' => 1_700_00, 'damage_excess_currency' => 'USD'], photos: 6);
    $sameType = pagedCar($this->staff, ['car_type_id' => $type->id]);
    pagedCar($this->staff);

    $this->get("/cars/{$car->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/car')
            ->where('car.name', 'Toyota Prado TX')
            ->has('car.gallery', 6)
            ->has('car.mileage_limit_km')
            ->where('car.damage_excess.currency', 'USD')
            ->missing('car.car_value_minor')
            ->missing('car.metadata')
            ->where('reservation_percent', 25)
            ->has('related', 2)
            ->where('related.0.slug', $sameType->slug)
            ->where('related', fn ($related) => collect($related)->doesntContain('slug', $car->slug)));
});

it('has no page for a draft or deleted car', function () {
    $draft = Car::factory()->create();
    $deleted = pagedCar($this->staff);
    $deleted->delete();

    $this->get("/cars/{$draft->slug}")->assertNotFound();
    $this->get("/cars/{$deleted->slug}")->assertNotFound();
    $this->get('/cars/no-such-car')->assertNotFound();
});
