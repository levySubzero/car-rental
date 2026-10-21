<?php

use App\Enums\Permission;
use App\Models\Currency;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PricingFixtures;

beforeEach(fn () => PricingFixtures::currencies());

it('lists currencies in their order and marks the base', function () {
    $this->actingAs(staffWith([Permission::CurrenciesView]))
        ->get(route('admin.currencies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/currencies/index')
            ->has('currencies', 5)
            ->where('currencies.0.code', 'KES')
            ->where('currencies.0.is_base', true)
            ->where('currencies.1.is_base', false));
});

it('creates a currency with an upper-cased ISO code', function () {
    $this->actingAs(staffWith([Permission::CurrenciesCreate]))
        ->post(route('admin.currencies.store'), ['code' => ' aud ', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'decimals' => 2, 'is_active' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.currencies.index'));

    expect(Currency::firstWhere('code', 'AUD'))->name->toBe('Australian Dollar')->is_active->toBeFalse();
});

it('validates currency input', function () {
    $this->actingAs(staffWith([Permission::CurrenciesCreate]))
        ->post(route('admin.currencies.store'), ['code' => 'US', 'name' => '', 'symbol' => '', 'decimals' => 3, 'is_active' => 'x'])
        ->assertSessionHasErrors(['code', 'name', 'symbol', 'decimals', 'is_active']);

    $this->actingAs(staffWith([Permission::CurrenciesCreate]))
        ->post(route('admin.currencies.store'), ['code' => 'USD', 'name' => 'Dup', 'symbol' => '$', 'decimals' => 2, 'is_active' => true])
        ->assertSessionHasErrors(['code']);
});

it('updates a currency but never its code', function () {
    $usd = Currency::firstWhere('code', 'USD');

    $this->actingAs(staffWith([Permission::CurrenciesUpdate]))
        ->put(route('admin.currencies.update', $usd), ['code' => 'XXX', 'name' => 'United States Dollar', 'symbol' => 'US$', 'decimals' => 2, 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($usd->fresh())->code->toBe('USD')->symbol->toBe('US$');
    $this->assertDatabaseHas('activity_log', ['log_name' => 'currencies', 'subject_id' => $usd->id, 'event' => 'updated']);
});

it('refuses to deactivate the base currency', function () {
    $kes = Currency::firstWhere('code', 'KES');

    $this->actingAs(staffWith([Permission::CurrenciesUpdate]))
        ->put(route('admin.currencies.active', $kes), ['value' => false])
        ->assertRedirect();

    expect($kes->fresh()->is_active)->toBeTrue();
});

it('toggles and reorders currencies', function () {
    $usd = Currency::firstWhere('code', 'USD');
    $gbp = Currency::firstWhere('code', 'GBP');
    $staff = staffWith([Permission::CurrenciesUpdate]);

    $this->actingAs($staff)->put(route('admin.currencies.active', $usd), ['value' => false])->assertRedirect();
    expect($usd->fresh()->is_active)->toBeFalse();

    $this->actingAs($staff)->put(route('admin.currencies.reorder'), ['ids' => [$gbp->id, $usd->id]])->assertRedirect();
    expect(Currency::query()->ordered()->pluck('code')->take(2)->all())->toBe(['GBP', 'USD']);
});

it('needs the matching permission for each currency action', function () {
    $usd = Currency::firstWhere('code', 'USD');
    $viewer = staffWith([Permission::CurrenciesView]);

    $this->actingAs($viewer)->get(route('admin.currencies.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.currencies.update', $usd), ['name' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.currencies.active', $usd), ['value' => false])->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.currencies.index'))->assertRedirect(route('login'));
});
