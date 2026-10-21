<?php

use App\Enums\Permission;
use App\Models\CancellationTier;
use Inertia\Testing\AssertableInertia as Assert;

it('lists tiers with the longest notice first', function () {
    CancellationTier::factory()->create(['hours_before_pickup' => 48]);
    CancellationTier::factory()->create(['hours_before_pickup' => 168]);

    $this->actingAs(staffWith([Permission::CancellationTiersView]))
        ->get(route('admin.cancellation-tiers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cancellation-tiers/index')
            ->where('tiers.0.hours_before_pickup', 168));
});

it('creates, updates and deletes a tier', function () {
    $staff = staffWith([Permission::CancellationTiersCreate, Permission::CancellationTiersUpdate, Permission::CancellationTiersDelete]);

    $this->actingAs($staff)
        ->post(route('admin.cancellation-tiers.store'), ['hours_before_pickup' => 168, 'refund_percent' => '100', 'label' => null])
        ->assertSessionHasNoErrors();

    $tier = CancellationTier::sole();
    expect($tier->refund_percent_bp)->toBe(10_000);

    $this->actingAs($staff)
        ->put(route('admin.cancellation-tiers.update', $tier), ['hours_before_pickup' => 72, 'refund_percent' => '50', 'label' => '3+ days'])
        ->assertSessionHasNoErrors();
    expect($tier->fresh())->hours_before_pickup->toBe(72)->refund_percent_bp->toBe(5_000);

    $this->actingAs($staff)->delete(route('admin.cancellation-tiers.destroy', $tier))->assertRedirect();
    expect(CancellationTier::count())->toBe(0);
});

it('validates tiers', function () {
    CancellationTier::factory()->create(['hours_before_pickup' => 48]);

    $this->actingAs(staffWith([Permission::CancellationTiersCreate]))
        ->post(route('admin.cancellation-tiers.store'), ['hours_before_pickup' => 48, 'refund_percent' => '101'])
        ->assertSessionHasErrors(['hours_before_pickup', 'refund_percent']);
});

it('needs the matching permission for each tier action', function () {
    $tier = CancellationTier::factory()->create();
    $viewer = staffWith([Permission::CancellationTiersView]);

    $this->actingAs($viewer)->get(route('admin.cancellation-tiers.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.cancellation-tiers.update', $tier), [])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.cancellation-tiers.destroy', $tier))->assertForbidden();
});
