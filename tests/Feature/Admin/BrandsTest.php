<?php

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\Car;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => CatalogFixtures::setUp());

it('lists brands with their car counts and the marquee order', function () {
    $toyota = Brand::factory()->create(['name' => 'Toyota']);
    Brand::factory()->create(['name' => 'BMW']);
    Car::factory()->for($toyota)->count(2)->create();

    $this->actingAs(staffWith([Permission::BrandsView]))
        ->get(route('admin.brands.index', ['sort' => 'name']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/brands/index')
            ->has('brands.data', 2)
            ->where('brands.data.0.name', 'BMW')
            ->where('brands.data.1.cars_count', 2)
            ->where('marquee.0.name', 'Toyota'));
});

it('creates a brand with a logo and logs it', function () {
    $editor = staffWith([Permission::BrandsCreate, Permission::BrandsUpdate, Permission::MediaUpload]);
    $logo = CatalogFixtures::photo($editor, 'brand.logo');

    $this->actingAs($editor)
        ->post(route('admin.brands.store'), [
            'name' => 'Land Rover',
            'slug' => '',
            'show_in_marquee' => true,
            'is_active' => true,
            'logo' => $logo->uuid,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.brands.edit', 'land-rover'));

    $brand = Brand::firstWhere('slug', 'land-rover');

    expect($brand->logo->uuid)->toBe($logo->uuid)
        ->and($brand->sort_order)->toBe(1);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'brands', 'subject_id' => $brand->id, 'event' => 'created']);
});

it('validates brand input', function () {
    Brand::factory()->create(['name' => 'Toyota']);
    $other = staffWith([Permission::MediaUpload, Permission::BrandsUpdate]);

    $this->actingAs(staffWith([Permission::BrandsCreate]))
        ->post(route('admin.brands.store'), [
            'name' => 'Toyota',
            'slug' => 'Not A Slug',
            'show_in_marquee' => 'maybe',
            'is_active' => true,
            'logo' => CatalogFixtures::photo($other, 'brand.logo')->uuid,
        ])
        ->assertSessionHasErrors(['name', 'slug', 'show_in_marquee', 'logo']);
});

it('keeps the slug when it is left blank and replaces or removes the logo', function () {
    $editor = staffWith([Permission::BrandsUpdate, Permission::MediaUpload]);
    $brand = Brand::factory()->create(['name' => 'Mercedes']);
    $first = CatalogFixtures::photo($editor, 'brand.logo');

    $save = fn (?string $logo) => $this->actingAs($editor)->put(route('admin.brands.update', $brand), [
        'name' => 'Mercedes-Benz', 'slug' => '', 'show_in_marquee' => false, 'is_active' => true, 'logo' => $logo,
    ])->assertSessionHasNoErrors();

    $save($first->uuid);
    expect($brand->fresh())->name->toBe('Mercedes-Benz')->slug->toBe('mercedes')->show_in_marquee->toBeFalse()
        ->and($brand->fresh()->logo->uuid)->toBe($first->uuid);

    $save(null);
    expect($brand->fresh()->logo)->toBeNull()
        ->and($first->fresh()->trashed())->toBeTrue();
});

it('reorders the marquee and toggles a brand in or out of it', function () {
    [$a, $b, $c] = Brand::factory()->count(3)->create();
    $editor = staffWith([Permission::BrandsUpdate]);

    $this->actingAs($editor)->put(route('admin.brands.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertRedirect();
    $this->actingAs($editor)->put(route('admin.brands.marquee', $a), ['value' => false])->assertRedirect();

    expect(Brand::query()->ordered()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
        ->and(Brand::query()->inMarquee()->pluck('id')->all())->toBe([$c->id, $b->id]);
});

it('refuses to delete a brand that cars use, even deleted ones', function () {
    $brand = Brand::factory()->create();
    Car::factory()->for($brand)->create()->delete();

    $this->actingAs(staffWith([Permission::BrandsDelete]))->delete(route('admin.brands.destroy', $brand));

    expect($brand->fresh())->not->toBeNull();
});

it('deletes an unused brand', function () {
    $brand = Brand::factory()->create();

    $this->actingAs(staffWith([Permission::BrandsDelete]))
        ->delete(route('admin.brands.destroy', $brand))
        ->assertRedirect(route('admin.brands.index'));

    expect(Brand::find($brand->id))->toBeNull();
});

it('needs the matching permission for each brand action', function () {
    $brand = Brand::factory()->create();
    $viewer = staffWith([Permission::BrandsView]);

    $this->actingAs($viewer)->get(route('admin.brands.edit', $brand))->assertOk();
    $this->actingAs($viewer)->get(route('admin.brands.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.brands.update', $brand), ['name' => 'x', 'show_in_marquee' => true, 'is_active' => true])->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.brands.reorder'), ['ids' => [$brand->id]])->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.brands.marquee', $brand), ['value' => false])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.brands.destroy', $brand))->assertForbidden();
});
