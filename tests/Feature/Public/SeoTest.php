<?php

use App\Http\Resources\Media\MediaResource;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Storage;
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

describe('link previews', function () {
    it('prints a car\'s preview tags in the page itself when SSR is off', function () {
        config(['inertia.ssr.enabled' => false]);

        $og = $this->car->gallery()->first()->conversions['og'];

        $this->get('/cars/toyota-prado-tx')
            ->assertOk()
            ->assertSee('<meta property="og:title" content="', false)
            ->assertSee('<meta property="og:url" content="'.rtrim(config('app.url'), '/').'/cars/toyota-prado-tx">', false)
            ->assertSee('<meta property="og:image" content="'.Storage::disk('media-public-local')->url($og).'">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta name="twitter:title" content="', false)
            ->assertSeeInOrder([' seats, ', ' '.$this->car->effectiveMileageLimitKm().' km per day included.'], false);
    });

    it('prints preview tags on the fleet page', function () {
        config(['inertia.ssr.enabled' => false]);

        $this->get('/fleet')->assertOk()->assertSee('<meta property="og:title" content="', false);
    });

    it('makes a 1200 by 630 JPEG for previews', function () {
        $media = $this->car->gallery()->first();
        $path = $media->conversions['og'];
        $size = getimagesizefromstring(Storage::disk($media->disk)->get($path));

        expect($path)->toEndWith('/og.jpg')
            ->and([$size[0], $size[1], $size['mime']])->toBe([1200, 630, 'image/jpeg']);
    });

    it('rebuilds conversions for existing photos, adding the preview image', function () {
        $media = $this->car->gallery()->first();
        $conversions = $media->conversions;
        unset($conversions['og']);
        $media->forceFill(['conversions' => $conversions])->save();

        expect(MediaResource::make($media->fresh())->resolve(request())['urls'])->not->toHaveKey('og');

        $this->artisan('media:regenerate')->assertSuccessful();

        expect($media->fresh()->conversions)->toHaveKey('og');
    });
});
