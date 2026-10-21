<?php

use App\Enums\FxRateSource;
use App\Enums\Permission;
use App\Models\FxRate;
use App\Services\Pricing\Fx\OpenExchangeRatesProvider;
use App\Services\Pricing\FxRateService;
use App\Settings\FxSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    PricingFixtures::currencies();
    config()->set('services.fx.api_key', 'test-app-id');

    $settings = app(FxSettings::class);
    $settings->provider = 'open_exchange_rates';
    $settings->save();
});

/**
 * @param  array<string, int|float>  $rates  units per USD
 */
function oxr(array $rates = ['KES' => 129.25, 'GBP' => 0.7891, 'EUR' => 0.9215, 'AED' => 3.672538], ?int $timestamp = null): void
{
    Http::fake([OpenExchangeRatesProvider::URL.'*' => Http::response([
        'disclaimer' => 'Usage subject to terms',
        'license' => 'https://openexchangerates.org/license',
        'timestamp' => $timestamp ?? now()->subMinutes(10)->timestamp,
        'base' => 'USD',
        'rates' => $rates,
    ])]);
}

it('fetches USD-based quotes and stores exact KES cross rates', function () {
    oxr();

    $result = app(FxRateService::class)->refresh();

    expect($result->failed())->toBeFalse()
        ->and(FxRate::query()->pluck('market_rate', 'currency_code')->all())->toBe([
            'USD' => '129.250000',
            'GBP' => '163.794196',
            'EUR' => '140.260445',
            'AED' => '35.193645',
        ]);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), OpenExchangeRatesProvider::URL)
        && $request['app_id'] === 'test-app-id'
        && $request['symbols'] === 'KES,USD,GBP,EUR,AED');
});

it('stores the provider timestamp as the rate time', function () {
    $asOf = now()->subMinutes(42)->startOfSecond();
    oxr(timestamp: $asOf->timestamp);

    app(FxRateService::class)->refresh();

    expect(FxRate::firstWhere('currency_code', 'USD')->fetched_at->equalTo($asOf))->toBeTrue();
});

it('rejects the whole fetch when the response cannot be trusted', function (array $body) {
    Http::fake([OpenExchangeRatesProvider::URL.'*' => Http::response($body)]);

    $result = app(FxRateService::class)->refresh();

    expect($result->failed())->toBeTrue()->and(FxRate::count())->toBe(0);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'fx', 'event' => 'refresh-failed']);
})->with([
    'missing symbol' => [['timestamp' => time(), 'base' => 'USD', 'rates' => ['KES' => 129.25, 'GBP' => 0.79, 'EUR' => 0.92]]],
    'zero rate' => [['timestamp' => time(), 'base' => 'USD', 'rates' => ['KES' => 129.25, 'GBP' => 0, 'EUR' => 0.92, 'AED' => 3.67]]],
    'string rate' => [['timestamp' => time(), 'base' => 'USD', 'rates' => ['KES' => '129.25', 'GBP' => 0.79, 'EUR' => 0.92, 'AED' => 3.67]]],
    'other base' => [['timestamp' => time(), 'base' => 'EUR', 'rates' => ['KES' => 140, 'GBP' => 0.85, 'USD' => 1.08, 'AED' => 3.9]]],
    'future timestamp' => [['timestamp' => time() + 3600, 'base' => 'USD', 'rates' => ['KES' => 129.25, 'GBP' => 0.79, 'EUR' => 0.92, 'AED' => 3.67]]],
    'no rates' => [['error' => true]],
]);

it('reports HTTP errors with the provider description', function () {
    Http::fake([OpenExchangeRatesProvider::URL.'*' => Http::response(['error' => true, 'status' => 401, 'message' => 'invalid_app_id', 'description' => 'Invalid App ID provided.'], 401)]);

    $result = app(FxRateService::class)->refresh();

    expect($result->failed())->toBeTrue()->and($result->message)->toContain('Invalid App ID provided.');
});

it('reports timeouts', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(app(FxRateService::class)->refresh()->message)->toContain('timed out');
});

it('fails without an API key', function () {
    config()->set('services.fx.api_key', null);
    Http::fake();

    expect(app(FxRateService::class)->refresh()->message)->toContain('FX_API_KEY');
    Http::assertNothingSent();
});

it('does nothing while the provider is manual', function () {
    $settings = app(FxSettings::class);
    $settings->provider = 'manual';
    $settings->save();
    Http::fake();

    expect(app(FxRateService::class)->refresh()->status)->toBe('skipped');
    Http::assertNothingSent();
});

it('waits refresh_minutes between fetches unless forced', function () {
    oxr();
    $fx = app(FxRateService::class);

    $fx->refresh();
    expect($fx->refresh()->status)->toBe('skipped');

    $this->travel(61)->minutes();
    expect($fx->refresh()->status)->toBe('refreshed');
    expect($fx->refresh(force: true)->status)->toBe('refreshed');
    Http::assertSentCount(3);
});

it('does not store a duplicate when the provider has nothing newer', function () {
    $asOf = now()->subMinutes(10)->timestamp;
    oxr(timestamp: $asOf);

    app(FxRateService::class)->refresh();
    app(FxRateService::class)->refresh(force: true);

    expect(FxRate::count())->toBe(4);
});

it('holds back a rate that jumped more than max_jump_percent until accepted', function () {
    FxRate::factory()->create(['currency_code' => 'USD', 'market_rate' => '129', 'fetched_at' => now()->subHours(2)]);
    oxr(['KES' => 145.0, 'GBP' => 0.7891, 'EUR' => 0.9215, 'AED' => 3.672538]);
    $fx = app(FxRateService::class);

    $result = $fx->refresh();

    expect($result->rejected)->toHaveKey('USD')
        ->and($result->rejected['USD']['change'])->toBe('12.40')
        ->and(FxRate::where('currency_code', 'USD')->count())->toBe(1)
        ->and($fx->lastRefresh()['rejected'])->toHaveKey('USD');

    $fx->refresh(force: true, acceptJumps: true);

    expect(FxRate::where('currency_code', 'USD')->latestFirst()->first()->market_rate)->toBe('145.000000');
});

it('runs from the console and on demand from the admin', function () {
    oxr();

    $this->artisan('fx:refresh')->assertSuccessful();
    expect(FxRate::where('source', FxRateSource::Api)->count())->toBe(4);

    $this->actingAs(staffWith([Permission::FxRatesOverride]))
        ->post(route('admin.fx-rates.refresh'), ['accept_jumps' => false])
        ->assertRedirect(route('admin.fx-rates.index'));

    Http::assertSentCount(2);
});

it('exits with failure from the console when the fetch fails', function () {
    Http::fake([OpenExchangeRatesProvider::URL.'*' => Http::response([], 500)]);

    $this->artisan('fx:refresh')->assertFailed();
});
