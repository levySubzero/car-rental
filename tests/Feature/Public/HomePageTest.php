<?php

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use Database\Seeders\CmsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->seed(CmsSeeder::class);
    $this->staff = User::factory()->staff()->create();
});

function publishedCar(User $staff, array $attributes = []): Car
{
    $car = Car::factory()->published()->create($attributes);
    app(MediaService::class)->sync($car, 'car.gallery', [CatalogFixtures::photo($staff)->uuid]);

    return $car;
}

it('renders the home page with the settings and seeded copy', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/home')
            ->where('hero.headline', 'Executive car rental and safaris in Nairobi')
            ->where('hero.media', null)
            ->where('marquee_position', 'page_bottom')
            ->has('trust_figures', 4)
            ->where('content.about_heading', 'Executive mobility in Nairobi since 2023')
            ->has('content.why_choose_us', 4)
            ->where('sections', ['services', 'featured_fleet', 'car_types', 'why_choose_us', 'trust_strip', 'requirements', 'about', 'policy_highlights', 'contact_cta'])
            ->where('brands.0.name', 'Mercedes-Benz')
            ->has('brands', 6)
            ->has('services', 6),
        );
});

it('shares the site chrome and the display currency', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.company_name', app(GeneralSettings::class)->company_name)
            ->where('site.whatsapp_number', '+254724422051')
            ->where('site.socials.instagram', 'https://www.instagram.com/bainesexecutives/')
            ->has('site.car_types', 7)
            ->has('site.brands', 6)
            ->has('currency.options'),
        );
});

it('lists published cars only, featured first, and groups them by car type', function () {
    $economy = CarType::firstWhere('slug', 'economy');
    $suvs = CarType::firstWhere('slug', 'suvs');

    $plain = publishedCar($this->staff, ['name' => 'Plain Car', 'car_type_id' => $economy->id]);
    $featured = publishedCar($this->staff, ['name' => 'Star Car', 'car_type_id' => $suvs->id, 'is_featured' => true]);
    Car::factory()->create(['name' => 'Draft Car', 'car_type_id' => $economy->id]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('featured', 2)
            ->where('featured.0.name', 'Star Car')
            ->where('featured.1.name', 'Plain Car')
            ->has('featured.0.cover.urls.card')
            ->missing('featured.0.car_value_minor')
            // Only types with published cars get a row, in car type order.
            ->has('car_type_rows', 2)
            ->where('car_type_rows.0.type.slug', 'suvs')
            ->where('car_type_rows.1.type.slug', 'economy')
            ->has('car_type_rows.1.cars', 1),
        );
});

it('caps each car type row at four cars', function () {
    $economy = CarType::firstWhere('slug', 'economy');

    foreach (range(1, 5) as $i) {
        publishedCar($this->staff, ['name' => "Economy {$i}", 'car_type_id' => $economy->id]);
    }

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('car_type_rows.0.cars', 4));
});

it('follows the section order and sends nothing for hidden sections', function () {
    publishedCar($this->staff);

    $home = app(HomeSettings::class);
    $home->sections = [
        ['key' => 'contact_cta', 'visible' => true],
        ['key' => 'about', 'visible' => true],
        ['key' => 'featured_fleet', 'visible' => false],
        ['key' => 'car_types', 'visible' => false],
        ['key' => 'services', 'visible' => false],
    ];
    $home->save();

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections', ['contact_cta', 'about'])
            ->has('featured', 0)
            ->has('car_type_rows', 0)
            ->has('services', 0),
        );
});

it('leaves inactive brands out of the marquee', function () {
    Brand::firstWhere('slug', 'bmw')->update(['is_active' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('brands', 5)
        ->has('site.brands', 5));
});

it('leaves a hidden car type out of the menus and rows but keeps its cars', function () {
    $suvs = CarType::firstWhere('slug', 'suvs');
    $suvs->update(['is_active' => false]);
    publishedCar($this->staff, ['name' => 'Hidden Type Car', 'car_type_id' => $suvs->id, 'is_featured' => true]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('site.car_types', 6)
            ->where('site.car_types', fn ($types) => collect($types)->doesntContain('slug', 'suvs'))
            ->has('car_type_rows', 0)
            // Visibility is about the type; the car is still featured and listed.
            ->where('featured.0.name', 'Hidden Type Car'),
        );
});
