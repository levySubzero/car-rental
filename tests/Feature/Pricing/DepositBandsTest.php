<?php

use App\Enums\Permission;
use App\Models\DepositBand;
use Inertia\Testing\AssertableInertia as Assert;

it('lists deposit bands by age', function () {
    DepositBand::factory()->create(['min_age' => 24, 'max_age' => 70, 'percent_bp' => 200]);
    DepositBand::factory()->create(['min_age' => 23, 'max_age' => 23, 'percent_bp' => 300]);

    $this->actingAs(staffWith([Permission::DepositBandsView]))
        ->get(route('admin.deposit-bands.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/deposit-bands/index')
            ->where('bands.0.min_age', 23)
            ->where('bands.1.percent_bp', 200));
});

it('creates a band with the percentage in basis points', function () {
    $this->actingAs(staffWith([Permission::DepositBandsCreate]))
        ->post(route('admin.deposit-bands.store'), ['min_age' => 23, 'max_age' => 23, 'percent' => '2.5', 'label' => 'Young driver', 'is_active' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.deposit-bands.index'));

    expect(DepositBand::sole())->percent_bp->toBe(250)->label->toBe('Young driver');
});

it('refuses active bands that overlap', function (int $min, int $max) {
    DepositBand::factory()->create(['min_age' => 24, 'max_age' => 70]);

    $this->actingAs(staffWith([Permission::DepositBandsCreate]))
        ->post(route('admin.deposit-bands.store'), ['min_age' => $min, 'max_age' => $max, 'percent' => '3', 'is_active' => true])
        ->assertSessionHasErrors('min_age');
})->with([
    'inside' => [30, 40],
    'lower edge' => [20, 24],
    'upper edge' => [70, 75],
    'covering' => [18, 80],
]);

it('allows adjacent bands, inactive overlaps and editing a band in place', function () {
    $band = DepositBand::factory()->create(['min_age' => 24, 'max_age' => 70]);
    $staff = staffWith([Permission::DepositBandsCreate, Permission::DepositBandsUpdate]);

    $this->actingAs($staff)
        ->post(route('admin.deposit-bands.store'), ['min_age' => 23, 'max_age' => 23, 'percent' => '3', 'is_active' => true])
        ->assertSessionHasNoErrors();

    $this->actingAs($staff)
        ->post(route('admin.deposit-bands.store'), ['min_age' => 30, 'max_age' => 40, 'percent' => '3', 'is_active' => false])
        ->assertSessionHasNoErrors();

    $this->actingAs($staff)
        ->put(route('admin.deposit-bands.update', $band), ['min_age' => 24, 'max_age' => 75, 'percent' => '2', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($band->fresh()->max_age)->toBe(75);
});

it('validates deposit bands', function () {
    $this->actingAs(staffWith([Permission::DepositBandsCreate]))
        ->post(route('admin.deposit-bands.store'), ['min_age' => 40, 'max_age' => 30, 'percent' => '100', 'is_active' => true])
        ->assertSessionHasErrors(['max_age', 'percent']);
});

it('needs the matching permission for each deposit band action', function () {
    $band = DepositBand::factory()->create();
    $viewer = staffWith([Permission::DepositBandsView]);

    $this->actingAs($viewer)->get(route('admin.deposit-bands.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.deposit-bands.update', $band), [])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.deposit-bands.destroy', $band))->assertForbidden();
});
