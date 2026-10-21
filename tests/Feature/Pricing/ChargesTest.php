<?php

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\Permission;
use App\Models\Charge;
use App\Models\Currency;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PricingFixtures;

beforeEach(fn () => PricingFixtures::currencies());

function chargePayload(array $overrides = []): array
{
    return [
        'code' => 'airport_pickup',
        'name' => 'Airport pickup',
        'description' => null,
        'basis' => 'per_booking',
        'amount' => '3500',
        'currency_code' => 'KES',
        'tax_inclusive' => true,
        'payable_at' => 'reservation',
        'applies' => 'extra',
        'is_active' => true,
        ...$overrides,
    ];
}

it('lists charges in their order', function () {
    Charge::factory()->chauffeur()->create();
    Charge::factory()->create(['name' => 'Valeting', 'basis' => ChargeBasis::InfoOnly, 'amount_minor' => null]);

    $this->actingAs(staffWith([Permission::ChargesView]))
        ->get(route('admin.charges.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/charges/index')
            ->has('charges', 2)
            ->where('charges.0.amount.display', 'USD 50')
            ->where('charges.1.amount', null));
});

it('creates a charge with the amount in minor units of its currency', function () {
    $this->actingAs(staffWith([Permission::ChargesCreate]))
        ->post(route('admin.charges.store'), chargePayload(['currency_code' => 'USD', 'amount' => '49.99', 'applies' => 'always']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.charges.index'));

    expect(Charge::firstWhere('code', 'airport_pickup'))
        ->amount_minor->toBe(4_999)
        ->currency_code->toBe('USD')
        ->applies->toBe(ChargeApplies::Always);
});

it('lets info-only charges leave the amount empty but not quotable ones', function () {
    $staff = staffWith([Permission::ChargesCreate]);

    $this->actingAs($staff)
        ->post(route('admin.charges.store'), chargePayload(['basis' => 'info_only', 'amount' => '']))
        ->assertSessionHasNoErrors();
    expect(Charge::firstWhere('code', 'airport_pickup')->amount_minor)->toBeNull();

    $this->actingAs($staff)
        ->post(route('admin.charges.store'), chargePayload(['code' => 'per_day_thing', 'basis' => 'per_day', 'amount' => '']))
        ->assertSessionHasErrors('amount');
});

it('validates charge input', function () {
    Charge::factory()->chauffeur()->create();
    Currency::firstWhere('code', 'AED')->update(['is_active' => false]);

    $this->actingAs(staffWith([Permission::ChargesCreate]))
        ->post(route('admin.charges.store'), chargePayload([
            'code' => 'Bad Code',
            'basis' => 'monthly',
            'amount' => '-5',
            'currency_code' => 'AED',
            'payable_at' => 'never',
            'applies' => 'sometimes',
        ]))
        ->assertSessionHasErrors(['code', 'basis', 'amount', 'currency_code', 'payable_at', 'applies']);
});

it('updates, reorders and deletes charges', function () {
    $charge = Charge::factory()->chauffeur()->create();
    $other = Charge::factory()->create();
    $staff = staffWith([Permission::ChargesUpdate, Permission::ChargesDelete]);

    $this->actingAs($staff)
        ->put(route('admin.charges.update', $charge), chargePayload(['code' => 'ignored', 'basis' => 'per_day', 'amount' => '55', 'currency_code' => 'USD']))
        ->assertSessionHasNoErrors();
    expect($charge->fresh())->code->toBe('chauffeur')->amount_minor->toBe(5_500);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'charges', 'subject_id' => $charge->id, 'event' => 'updated']);

    $this->actingAs($staff)->put(route('admin.charges.reorder'), ['ids' => [$other->id, $charge->id]])->assertRedirect();
    expect(Charge::query()->ordered()->pluck('id')->all())->toBe([$other->id, $charge->id]);

    $this->actingAs($staff)->delete(route('admin.charges.destroy', $charge))->assertRedirect(route('admin.charges.index'));
    expect(Charge::find($charge->id))->toBeNull();
});

it('refuses to deactivate a currency that active charges use', function () {
    Charge::factory()->chauffeur()->create();
    $usd = Currency::firstWhere('code', 'USD');

    $this->actingAs(staffWith([Permission::CurrenciesUpdate]))
        ->put(route('admin.currencies.active', $usd), ['value' => false]);

    expect($usd->fresh()->is_active)->toBeTrue();
});

it('needs the matching permission for each charge action', function () {
    $charge = Charge::factory()->create();
    $viewer = staffWith([Permission::ChargesView]);

    $this->actingAs($viewer)->get(route('admin.charges.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.charges.update', $charge), chargePayload())->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.charges.reorder'), ['ids' => [$charge->id]])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.charges.destroy', $charge))->assertForbidden();
});
