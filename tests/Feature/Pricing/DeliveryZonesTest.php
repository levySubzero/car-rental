<?php

use App\Enums\Permission;
use App\Models\DeliveryZone;
use Inertia\Testing\AssertableInertia as Assert;

it('lists delivery zones with their fee or "quoted"', function () {
    DeliveryZone::factory()->create(['name' => 'Nairobi', 'fee_minor' => 200_000, 'is_default' => true]);
    DeliveryZone::factory()->quoted()->create(['name' => 'Outside Nairobi']);

    $this->actingAs(staffWith([Permission::DeliveryZonesView]))
        ->get(route('admin.delivery-zones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/delivery-zones/index')
            ->where('zones.0.fee.display', 'KES 2,000')
            ->where('zones.1.fee', null));
});

it('creates a zone and keeps a single default', function () {
    $nairobi = DeliveryZone::factory()->create(['name' => 'Nairobi', 'is_default' => true]);

    $this->actingAs(staffWith([Permission::DeliveryZonesCreate]))
        ->post(route('admin.delivery-zones.store'), ['name' => 'JKIA', 'description' => 'Airport', 'fee' => '2500', 'is_default' => true, 'is_active' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.delivery-zones.index'));

    expect(DeliveryZone::firstWhere('name', 'JKIA'))->fee_minor->toBe(250_000)->is_default->toBeTrue()
        ->and($nairobi->fresh()->is_default)->toBeFalse();
});

it('stores an empty fee as quoted by staff and never makes an inactive zone the default', function () {
    $zone = DeliveryZone::factory()->create();

    $this->actingAs(staffWith([Permission::DeliveryZonesUpdate]))
        ->put(route('admin.delivery-zones.update', $zone), ['name' => $zone->name, 'fee' => '', 'is_default' => true, 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($zone->fresh())->fee_minor->toBeNull()->is_default->toBeFalse();
});

it('validates delivery zones', function () {
    DeliveryZone::factory()->create(['name' => 'Nairobi']);

    $this->actingAs(staffWith([Permission::DeliveryZonesCreate]))
        ->post(route('admin.delivery-zones.store'), ['name' => 'Nairobi', 'fee' => '20.505', 'is_default' => 'x', 'is_active' => true])
        ->assertSessionHasErrors(['name', 'fee', 'is_default']);
});

it('needs the matching permission for each delivery zone action', function () {
    $zone = DeliveryZone::factory()->create();
    $viewer = staffWith([Permission::DeliveryZonesView]);

    $this->actingAs($viewer)->get(route('admin.delivery-zones.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.delivery-zones.update', $zone), ['name' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.delivery-zones.destroy', $zone))->assertForbidden();
});
