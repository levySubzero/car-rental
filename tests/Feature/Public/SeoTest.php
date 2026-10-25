<?php

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use App\Services\Media\MediaService;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $staff = User::factory()->staff()->create();
    $this->brand = Brand::factory()->create(['slug' => 'toyota']);
    $this->type = CarType::factory()->create(['slug' => 'suvs']);
    $this->car = Car::factory()->published()->create(['slug' => 'toyota-prado-tx', 'brand_id' => $this->brand->id, 'car_type_id' => $this->type->id]);
    app(MediaService::class)->sync($this->car, 'car.gallery', [CatalogFixtures::photo($staff)->uuid], actor: $staff);
    $this->draft = Car::factory()->create(['slug' => 'draft-car']);
    $this->hidden = CarType::factory()->create(['slug' => 'hidden-type', 'is_active' => false]);
    Car::factory()->published()->create(['car_type_id' => $this->hidden->id]);
});

it('lists the public pages, published cars and filled brand and type pages in the sitemap', function () {
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($response->getContent());
    // Absolute URLs; compared by path, since the test request's host differs from APP_URL.
    $urls = collect(iterator_to_array($xml->url, false));
    $paths = $urls->map(fn ($url) => parse_url((string) $url->loc, PHP_URL_PATH) ?? '/');

    expect($urls->every(fn ($url) => str_starts_with((string) $url->loc, 'http')))->toBeTrue()
        ->and($paths)->toContain('/', '/fleet', '/services', '/rental-policy', '/contact', '/privacy')
        ->toContain('/cars/toyota-prado-tx', '/car-brands/toyota', '/car-types/suvs')
        ->not->toContain('/cars/draft-car')
        ->not->toContain('/car-types/hidden-type');
});

it('points crawlers to the sitemap and keeps them out of the admin', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Sitemap: '.route('sitemap'), false);
});

it('shares the absolute site address for canonical links and previews', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('site.url', rtrim(config('app.url'), '/')));
});
