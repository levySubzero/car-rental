<?php

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->brand = Brand::factory()->create(['name' => 'Toyota']);
    $this->type = CarType::factory()->create(['name' => 'SUV']);

    $this->payload = fn (array $overrides = []) => [
        'brand_id' => $this->brand->id,
        'car_type_id' => $this->type->id,
        'name' => 'Toyota Prado TX',
        'slug' => '',
        'model_year' => 2023,
        'engine_type' => '2.8L Turbo Diesel',
        'engine_capacity_litres' => '2.8',
        'fuel_type' => 'diesel',
        'transmission' => 'automatic',
        'colour' => 'Pearl white',
        'doors' => 5,
        'seats' => 7,
        'daily_rate' => '15000',
        'car_value' => '8500000.50',
        'mileage_limit_km' => '',
        'damage_excess' => '',
        'damage_excess_currency' => 'KES',
        'description' => 'Seven seats.',
        'metadata' => ['drive' => '4WD', 'top_speed_kmh' => ' 175 ', 'empty' => ''],
        'gallery' => [],
        'cover' => null,
        ...$overrides,
    ];
});

function carEditor(array $extra = []): User
{
    return staffWith([Permission::CarsView, Permission::CarsCreate, Permission::CarsUpdate, Permission::MediaUpload, ...$extra]);
}

it('lists cars with filters', function () {
    Car::factory()->for($this->brand)->published()->create(['name' => 'Land Cruiser']);
    Car::factory()->create(['name' => 'Polo']);

    $this->actingAs(staffWith([Permission::CarsView]))
        ->get(route('admin.cars.index', ['brand' => $this->brand->id, 'status' => 'published']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cars/index')
            ->has('cars.data', 1)
            ->where('cars.data.0.name', 'Land Cruiser')
            ->where('cars.data.0.daily_rate.display', fn (string $display) => str_starts_with($display, 'KES '))
            ->missing('cars.data.0.car_value')
            ->has('brandOptions', 2));
});

it('creates a car with amounts in minor units, a gallery and extra info', function () {
    $editor = carEditor([Permission::CarsViewValue]);
    [$a, $b] = [CatalogFixtures::photo($editor), CatalogFixtures::photo($editor)];

    $this->actingAs($editor)
        ->post(route('admin.cars.store'), ($this->payload)(['gallery' => [$a->uuid, $b->uuid], 'cover' => $b->uuid]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.cars.edit', 'toyota-prado-tx'));

    $car = Car::firstWhere('slug', 'toyota-prado-tx');

    expect($car)
        ->daily_rate_minor->toBe(1_500_000)
        ->car_value_minor->toBe(850_000_050)
        ->damage_excess_minor->toBeNull()
        ->damage_excess_currency->toBeNull()
        ->mileage_limit_km->toBeNull()
        ->is_published->toBeFalse()
        ->and($car->metadata->getArrayCopy())->toBe(['drive' => '4WD', 'top_speed_kmh' => '175'])
        ->and($car->gallery->pluck('uuid')->all())->toBe([$a->uuid, $b->uuid])
        ->and($car->cover->uuid)->toBe($b->uuid);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'cars', 'subject_id' => $car->id, 'event' => 'created']);
});

it('never logs the car value', function () {
    $editor = carEditor([Permission::CarsViewValue]);

    $this->actingAs($editor)->post(route('admin.cars.store'), ($this->payload)())->assertSessionHasNoErrors();

    $logged = Activity::query()->where('log_name', 'cars')->get();

    expect($logged)->not->toBeEmpty()
        ->and(json_encode($logged->toArray()))->toContain('daily_rate_minor')->not->toContain('car_value');
});

it('validates car input', function () {
    $this->actingAs(carEditor())
        ->post(route('admin.cars.store'), ($this->payload)([
            'brand_id' => 999,
            'fuel_type' => 'steam',
            'doors' => 0,
            'daily_rate' => '15,000',
            'damage_excess' => '100000',
            'damage_excess_currency' => 'XYZ',
            'metadata' => ['Top Speed' => '250'],
            'cover' => fake()->uuid(),
        ]))
        ->assertSessionHasErrors(['brand_id', 'fuel_type', 'doors', 'daily_rate', 'damage_excess_currency', 'metadata', 'cover']);
});

it('rejects more extra info than allowed and non-text values', function () {
    $tooMany = collect(range(1, 31))->mapWithKeys(fn ($i) => ["key_{$i}" => 'x'])->all();

    $this->actingAs(carEditor())
        ->post(route('admin.cars.store'), ($this->payload)(['metadata' => $tooMany]))
        ->assertSessionHasErrors('metadata');

    $this->actingAs(carEditor())
        ->post(route('admin.cars.store'), ($this->payload)(['metadata' => ['features' => ['ac', 'carplay']]]))
        ->assertSessionHasErrors('metadata.features');
});

it('allows at most six photos, and only the uploader\'s own pending files', function () {
    $editor = carEditor();
    $photos = collect(range(1, 7))->map(fn () => CatalogFixtures::photo($editor)->uuid)->all();
    $someoneElses = CatalogFixtures::photo(carEditor())->uuid;

    $this->actingAs($editor)
        ->post(route('admin.cars.store'), ($this->payload)(['gallery' => $photos]))
        ->assertSessionHasErrors('gallery');

    $this->actingAs($editor)
        ->post(route('admin.cars.store'), ($this->payload)(['gallery' => [$someoneElses]]))
        ->assertSessionHasErrors('gallery.0');
});

it('ignores the car value from staff who cannot see it, and keeps the stored value', function () {
    $car = Car::factory()->withValue(5_000_000_00)->create(['brand_id' => $this->brand->id, 'car_type_id' => $this->type->id]);
    $editor = carEditor();

    $this->actingAs($editor)
        ->get(route('admin.cars.edit', $car))
        ->assertInertia(fn (Assert $page) => $page->missing('car.car_value'));

    $this->actingAs($editor)
        ->put(route('admin.cars.update', $car), ($this->payload)(['car_value' => '1']))
        ->assertSessionHasNoErrors();

    expect($car->fresh()->car_value_minor)->toBe(5_000_000_00);
});

it('shows the car value to staff with cars.view-value', function () {
    $car = Car::factory()->withValue(5_000_000_00)->create();

    $this->actingAs(staffWith([Permission::CarsView, Permission::CarsViewValue]))
        ->get(route('admin.cars.edit', $car))
        ->assertInertia(fn (Assert $page) => $page->where('car.car_value.minor', 5_000_000_00));
});

it('replaces the gallery on update and keeps a blank slug', function () {
    $editor = carEditor();
    [$a, $b] = [CatalogFixtures::photo($editor), CatalogFixtures::photo($editor)];

    $this->actingAs($editor)->post(route('admin.cars.store'), ($this->payload)(['gallery' => [$a->uuid]]));
    $car = Car::firstWhere('slug', 'toyota-prado-tx');

    $this->actingAs($editor)
        ->put(route('admin.cars.update', $car), ($this->payload)(['name' => 'Prado', 'gallery' => [$b->uuid, $a->uuid]]))
        ->assertSessionHasNoErrors();

    $car->refresh();

    expect($car->slug)->toBe('toyota-prado-tx')
        ->and($car->gallery->pluck('uuid')->all())->toBe([$b->uuid, $a->uuid])
        ->and($car->cover->uuid)->toBe($b->uuid);
});

it('publishes a car only once it has a photo', function () {
    $editor = carEditor([Permission::CarsPublish]);
    $car = Car::factory()->create();

    $this->actingAs($editor)->post(route('admin.cars.publish', $car));
    expect($car->fresh()->is_published)->toBeFalse();

    $photo = CatalogFixtures::photo($editor);
    $this->actingAs($editor)->put(route('admin.cars.update', $car), ($this->payload)(['name' => $car->name, 'gallery' => [$photo->uuid]]));

    $this->actingAs($editor)->post(route('admin.cars.publish', $car))->assertRedirect();
    expect($car->fresh())->is_published->toBeTrue()->published_at->not->toBeNull();

    $this->actingAs($editor)->delete(route('admin.cars.unpublish', $car))->assertRedirect();
    expect($car->fresh()->is_published)->toBeFalse();
});

it('toggles availability and featured', function () {
    $car = Car::factory()->create();
    $editor = carEditor();

    $this->actingAs($editor)->put(route('admin.cars.availability', $car), ['value' => false])->assertRedirect();
    $this->actingAs($editor)->put(route('admin.cars.featured', $car), ['value' => true])->assertRedirect();

    expect($car->fresh())->is_available->toBeFalse()->is_featured->toBeTrue();
});

it('soft-deletes a car with its photos, and keeps its slug taken', function () {
    $editor = carEditor([Permission::CarsDelete]);
    $photo = CatalogFixtures::photo($editor);
    $this->actingAs($editor)->post(route('admin.cars.store'), ($this->payload)(['gallery' => [$photo->uuid]]));
    $car = Car::firstWhere('slug', 'toyota-prado-tx');

    $this->actingAs($editor)->delete(route('admin.cars.destroy', $car))->assertRedirect(route('admin.cars.index'));

    expect($car->fresh()->trashed())->toBeTrue()
        ->and($photo->fresh()->trashed())->toBeTrue();

    $this->actingAs($editor)->post(route('admin.cars.store'), ($this->payload)())->assertSessionHasNoErrors();
    expect(Car::query()->latest('id')->first()->slug)->toBe('toyota-prado-tx-2');
});

it('needs the matching permission for each car action', function () {
    $car = Car::factory()->create();
    $viewer = staffWith([Permission::CarsView]);

    $this->actingAs($viewer)->get(route('admin.cars.edit', $car))->assertOk();
    $this->actingAs($viewer)->get(route('admin.cars.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.cars.update', $car), ($this->payload)())->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.cars.availability', $car), ['value' => false])->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.cars.publish', $car))->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.cars.destroy', $car))->assertForbidden();
    $this->actingAs(carEditor())->post(route('admin.cars.publish', $car))->assertForbidden();
});

it('stores the engine capacity in litres with one decimal', function () {
    $editor = carEditor();

    $this->actingAs($editor)->post(route('admin.cars.store'), ($this->payload)(['engine_capacity_litres' => '2.5']))->assertSessionHasNoErrors();
    expect(Car::firstWhere('slug', 'toyota-prado-tx')->engine_capacity_litres)->toBe('2.5');

    $this->actingAs($editor)
        ->post(route('admin.cars.store'), ($this->payload)(['engine_capacity_litres' => '2.55']))
        ->assertSessionHasErrors('engine_capacity_litres');
    $this->actingAs($editor)
        ->post(route('admin.cars.store'), ($this->payload)(['engine_capacity_litres' => '2494']))
        ->assertSessionHasErrors('engine_capacity_litres');
});
