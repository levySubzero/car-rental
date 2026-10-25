<?php

use App\Models\Brand;
use App\Models\CarType;
use App\Models\FxRate;
use App\Services\Pricing\DisplayCurrencyService;
use App\Settings\HomeSettings;
use App\Support\PublicCache;
use Database\Seeders\CmsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;
use Tests\Support\PricingFixtures;

/*
| M12.7: the public chrome and display currencies are cached between requests and cleared
| by the admin saves that change them.
*/

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->seed(CmsSeeder::class);
});

function carTypeQueries(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();

    return collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from `car_types`') || str_contains($query['query'], 'from "car_types"'))->count();
}

it('shares the chrome from the cache on later requests', function () {
    expect(carTypeQueries(fn () => $this->get(route('services'))->assertOk()))->toBeGreaterThan(0)
        ->and(Cache::has(PublicCache::SITE))->toBeTrue()
        ->and(carTypeQueries(fn () => $this->get(route('services'))->assertOk()))->toBe(0);
});

it('clears the chrome when staff save a car type, brand or the home settings', function (Closure $save) {
    $this->get(route('services'))->assertOk();

    $save();

    expect(Cache::has(PublicCache::SITE))->toBeFalse();
})->with([
    'car type' => [fn () => CarType::factory()->create()],
    'brand' => [fn () => Brand::query()->firstOrFail()->update(['name' => 'Renamed'])],
    'home settings' => [fn () => tap(app(HomeSettings::class))->save()],
]);

it('lists a new car type straight after it is saved', function () {
    $this->get(route('services'))->assertOk();

    CarType::factory()->create(['name' => 'Convertible', 'slug' => 'convertible', 'is_active' => true]);

    $this->get(route('services'))->assertInertia(fn (Assert $page) => $page
        ->where('site.car_types', fn ($types) => collect($types)->contains('slug', 'convertible')));
});

it('clears the display currencies when a rate is stored', function () {
    PricingFixtures::currencies();
    $display = app(DisplayCurrencyService::class);

    $before = $display->options();
    FxRate::factory()->create(['currency_code' => 'USD', 'market_rate' => '129', 'fetched_at' => now()]);

    expect(Cache::has(PublicCache::CURRENCIES))->toBeFalse()
        ->and($display->options())->not->toBe($before);
});
