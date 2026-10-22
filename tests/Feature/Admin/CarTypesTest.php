<?php

use App\Enums\Permission;
use App\Models\Car;
use App\Models\CarType;
use Inertia\Testing\AssertableInertia as Assert;

it('lists car types in their order with car counts', function () {
    $suv = CarType::factory()->create(['name' => 'SUV']);
    CarType::factory()->create(['name' => 'Van']);
    Car::factory()->for($suv)->create();

    $this->actingAs(staffWith([Permission::CarTypesView]))
        ->get(route('admin.car-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/car-types/index')
            ->has('types', 2)
            ->where('types.0.name', 'SUV')
            ->where('types.0.cars_count', 1));
});

it('creates and updates a car type', function () {
    $editor = staffWith([Permission::CarTypesCreate, Permission::CarTypesUpdate]);

    $this->actingAs($editor)
        ->post(route('admin.car-types.store'), ['name' => '4x4 Safari', 'slug' => '', 'description' => 'Game drives', 'is_active' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.car-types.index'));

    $type = CarType::firstWhere('slug', '4x4-safari');

    $this->actingAs($editor)
        ->put(route('admin.car-types.update', $type), ['name' => '4x4 Safari', 'slug' => 'safari', 'description' => null, 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($type->fresh())->slug->toBe('safari')->description->toBeNull()->is_active->toBeFalse();
    $this->assertDatabaseHas('activity_log', ['log_name' => 'car-types', 'subject_id' => $type->id, 'event' => 'updated']);
});

it('validates car type input', function () {
    CarType::factory()->create(['name' => 'SUV']);

    $this->actingAs(staffWith([Permission::CarTypesCreate]))
        ->post(route('admin.car-types.store'), ['name' => 'SUV', 'is_active' => 'x', 'description' => str_repeat('a', 1001)])
        ->assertSessionHasErrors(['name', 'is_active', 'description']);
});

it('reorders car types', function () {
    [$a, $b] = CarType::factory()->count(2)->create();

    $this->actingAs(staffWith([Permission::CarTypesUpdate]))
        ->put(route('admin.car-types.reorder'), ['ids' => [$b->id, $a->id]])
        ->assertRedirect();

    expect(CarType::query()->ordered()->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('refuses to delete a car type that cars use', function () {
    $type = CarType::factory()->create();
    Car::factory()->for($type)->create();

    $this->actingAs(staffWith([Permission::CarTypesDelete]))->delete(route('admin.car-types.destroy', $type));

    expect($type->fresh())->not->toBeNull();
});

it('needs the matching permission for each car type action', function () {
    $type = CarType::factory()->create();
    $viewer = staffWith([Permission::CarTypesView]);

    $this->actingAs($viewer)->get(route('admin.car-types.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.car-types.update', $type), ['name' => 'x', 'is_active' => true])->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.car-types.reorder'), ['ids' => [$type->id]])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.car-types.destroy', $type))->assertForbidden();
});

it('makes new car types visible on the website by default', function () {
    expect((new CarType)->is_active)->toBeTrue()
        ->and(CarType::create(['name' => 'Convertibles'])->fresh()->is_active)->toBeTrue();
});

it('shows and hides a car type from the list switch, independently of featured cars', function () {
    $type = CarType::factory()->create(['is_active' => true]);
    $car = Car::factory()->for($type)->create(['is_featured' => true]);
    $editor = staffWith([Permission::CarTypesView, Permission::CarTypesUpdate]);

    $this->actingAs($editor)
        ->from(route('admin.car-types.index'))
        ->put(route('admin.car-types.visibility', $type), ['value' => false])
        ->assertRedirect(route('admin.car-types.index'));

    expect($type->fresh()->is_active)->toBeFalse()
        ->and($car->fresh()->is_featured)->toBeTrue();

    $this->actingAs($editor)->put(route('admin.car-types.visibility', $type), ['value' => true]);

    expect($type->fresh()->is_active)->toBeTrue();
});

it('validates and authorizes the visibility switch', function () {
    $type = CarType::factory()->create();

    $this->actingAs(staffWith([Permission::CarTypesView]))
        ->put(route('admin.car-types.visibility', $type), ['value' => false])
        ->assertForbidden();

    $this->actingAs(staffWith([Permission::CarTypesUpdate]))
        ->put(route('admin.car-types.visibility', $type), ['value' => 'maybe'])
        ->assertSessionHasErrors('value');

    expect($type->fresh()->is_active)->toBeTrue();
});
