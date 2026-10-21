<?php

use App\Models\Currency;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    PricingFixtures::currencies();
    PricingFixtures::rates(['USD' => '129', 'GBP' => '163.5']);
});

it('shares the base and every active currency that has a rate', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currency.selected', 'KES')
            ->where('currency.options', [
                ['code' => 'KES', 'name' => 'Kenya Shilling', 'symbol' => 'KSh', 'decimals' => 0, 'effective' => null],
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'effective' => '126.000000'],
                ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimals' => 2, 'effective' => '160.500000'],
            ]));
});

it('remembers the chosen currency in a cookie', function () {
    $response = $this->from(route('home'))->post(route('currency.update'), ['currency' => 'USD']);

    $response->assertRedirect(route('home'))->assertCookie('currency', 'USD');

    $this->withCookie('currency', 'USD')->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('currency.selected', 'USD'));
});

it('refuses currencies that are inactive or have no rate', function (string $code) {
    Currency::firstWhere('code', 'GBP')->update(['is_active' => false]);

    $this->post(route('currency.update'), ['currency' => $code])->assertSessionHasErrors('currency');
})->with(['GBP', 'EUR', 'XYZ']);

it('falls back to the base when the cookie names a currency no longer offered', function () {
    $this->withCookie('currency', 'EUR')->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('currency.selected', 'KES'));
});
