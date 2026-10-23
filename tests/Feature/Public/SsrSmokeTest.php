<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use App\Services\Media\MediaService;
use Database\Seeders\CmsSeeder;
use Database\Seeders\PricingSeeder;
use Illuminate\Support\Facades\Process;
use Tests\Support\CatalogFixtures;

/*
 * M7.16 / M7.19: every public page renders through the production SSR bundle without
 * throwing (no window or document access during render). Needs `npm run build:ssr`;
 * skipped without the bundle or Node.
 */

beforeEach(function () {
    $this->bundle = base_path('bootstrap/ssr/app.js');

    if (! is_file($this->bundle) || ! Process::run(['node', '--version'])->successful()) {
        $this->markTestSkipped('Run `npm run build:ssr` (and install Node) to smoke-test SSR.');
    }

    CatalogFixtures::setUp();
    $this->seed(CmsSeeder::class);
    $this->seed(PricingSeeder::class);

    $staff = User::factory()->staff()->create();
    $this->car = Car::factory()->published()->create([
        'name' => 'Toyota Prado TX',
        'brand_id' => Brand::firstWhere('slug', 'toyota')->id,
        'car_type_id' => CarType::firstWhere('slug', 'suvs')->id,
    ]);
    app(MediaService::class)->sync($this->car, 'car.gallery', [CatalogFixtures::photo($staff)->uuid]);

    // Booking links: a draft opens the wizard, a submitted booking its status page.
    Booking::factory()->for($this->car)->withToken(str_repeat('d', 64))->create();
    Booking::factory()->for($this->car)->awaitingPayment()->withToken(str_repeat('s', 64))->create();
});

it('server-renders every public page', function (string $url, string $expect) {
    $url = strtr($url, ['{car}' => $this->car->slug, '{draft}' => str_repeat('d', 64), '{submitted}' => str_repeat('s', 64)]);

    $page = $this->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk()->json();

    $result = Process::input(json_encode($page))->timeout(60)
        ->run(['node', base_path('tests/fixtures/ssr-render.mjs'), $this->bundle]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $rendered = json_decode($result->output(), true);

    expect($rendered['body'])->toContain('<h1')->toContain($expect)
        ->and(implode('', $rendered['head']))->toContain('<title');
})->with([
    'home' => ['/', 'Executive car rental and safaris in Nairobi'],
    'fleet' => ['/fleet', 'Toyota Prado TX'],
    'car' => ['/cars/{car}', 'Toyota Prado TX'],
    'brand' => ['/car-brands/toyota', 'Toyota'],
    'car type' => ['/car-types/suvs', 'SUVs'],
    'services' => ['/services', 'Chauffeur-driven rental'],
    'about' => ['/about', 'About Baines Executives'],
    'rental policy' => ['/rental-policy', 'Booking and payment'],
    'contact' => ['/contact', 'Send us a message'],
    'privacy' => ['/privacy', 'Privacy notice'],
    'booking page' => ['/cars/{car}/book', 'Book the Toyota Prado TX'],
    'booking wizard' => ['/bookings/{draft}', 'Book the Toyota Prado TX'],
    'booking status' => ['/bookings/{submitted}', 'Awaiting payment'],
]);
