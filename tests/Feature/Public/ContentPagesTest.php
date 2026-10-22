<?php

use App\Enums\ChargeApplies;
use App\Enums\Permission;
use App\Models\CancellationTier;
use App\Models\Charge;
use App\Models\Page;
use App\Models\Policy;
use App\Models\Service;
use App\Services\Cms\PageService;
use Database\Seeders\CmsSeeder;
use Database\Seeders\PricingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->seed(CmsSeeder::class);
});

it('lists the active services with their anchors and calls to action', function () {
    Service::firstWhere('slug', 'airport-transfers')->update(['is_active' => false]);

    $this->get('/services')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/services')
            ->where('heading', 'Our services')
            ->has('services', 5)
            ->where('services.0.slug', 'self-drive-car-rental')
            ->where('services.0.cta.type', 'book')
            ->where('services', fn ($services) => collect($services)->doesntContain('slug', 'airport-transfers')
                && collect($services)->firstWhere('slug', 'safaris')['cta']['type'] === 'whatsapp'));
});

it('shows the About page, falling back to the home page story', function () {
    $this->get('/about')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/about')
            ->where('heading', 'About Baines Executives')
            ->where('body_html', fn (string $html) => str_contains($html, 'Nyaribari Issac'))
            ->has('reasons', 4)
            ->has('trust_figures', 4));

    $about = Page::firstWhere('slug', 'about');
    $about->update(['content' => [...$about->content, 'heading' => 'Our story', 'body' => 'Our **own** words.']]);

    $this->get('/about')->assertInertia(fn (Assert $page) => $page
        ->where('heading', 'Our story')
        ->where('body_html', fn (string $html) => str_contains($html, '<strong>own</strong>')));
});

it('shows a header image staff set on a page, with guidance in the editor', function () {
    $editor = staffWith([Permission::PagesView, Permission::PagesUpdate, Permission::MediaUpload]);
    $photo = CatalogFixtures::photo($editor, 'page.image');
    $contact = Page::firstWhere('slug', 'contact');

    $this->actingAs($editor)
        ->get(route('admin.pages.edit', 'contact'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.fields', fn ($fields) => collect($fields)->firstWhere('key', 'header_image')['guidance']['width'] === 2400)
            ->where('imageLimits.guidance.shape', 'Landscape 4:3'));

    app(PageService::class)->update($contact, [...$contact->content, 'header_image' => $photo->uuid], [], $editor);

    $this->get('/contact')->assertInertia(fn (Assert $page) => $page
        ->has('header_image.urls.full')
        ->where('header_image.urls.full', fn (string $url) => str_contains($url, $photo->uuid)));

    foreach (['/services', '/rental-policy', '/about', '/fleet'] as $url) {
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('header_image', null));
    }
});

it('shows the privacy notice once written', function () {
    $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/privacy')
        ->where('heading', 'Privacy notice')
        ->where('body_html', ''));

    $privacy = Page::firstWhere('slug', 'privacy');
    $privacy->update(['content' => [...$privacy->content, 'body' => 'We keep your data safe.']]);

    $this->get('/privacy')->assertInertia(fn (Assert $page) => $page
        ->where('body_html', fn (string $html) => str_contains($html, 'We keep your data safe.')));
});

it('shows the published policies by group with the current rates', function () {
    $this->seed(PricingSeeder::class);
    CancellationTier::create(['hours_before_pickup' => 48, 'refund_percent_bp' => 10000, 'label' => 'More than 48 hours before pick-up']);
    Charge::query()->where('applies', ChargeApplies::Listed)->first()?->update(['is_active' => false]);
    $draftOnly = Policy::factory()->create(['title' => 'Unpublished policy']);

    $this->get('/rental-policy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/rental-policy')
            ->where('heading', 'Rental policy')
            ->where('groups.0.key', 'booking')
            ->where('groups.0.label', 'Booking and payment')
            ->where('groups', fn ($groups) => collect($groups)->sum(fn ($group) => count($group['policies'])) === 31
                && collect($groups)->flatMap(fn ($group) => $group['policies'])->doesntContain('slug', $draftOnly->slug))
            ->where('cancellation_tiers.0.refund_percent', 100)
            ->has('fees')
            ->where('fees', fn ($fees) => collect($fees)->every(fn ($fee) => isset($fee['name'], $fee['basis'], $fee['payable_at'])))
            ->has('deposit_bands', 2)
            ->has('delivery_zones', 2));
});
