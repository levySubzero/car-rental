<?php

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\CarType;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\PolicyBundle;
use App\Models\PolicyVersion;
use App\Models\Service;
use App\Models\User;
use App\Services\Cms\PolicyService;
use App\Settings\GeneralSettings;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

it('loads the seed pack once', function () {
    $this->seed(CmsSeeder::class);
    $this->seed(CmsSeeder::class);

    expect(Brand::count())->toBe(30)
        ->and(CarType::count())->toBe(7)
        ->and(Service::count())->toBe(6)
        ->and(Page::count())->toBe(7)
        ->and(Policy::count())->toBe(31)
        ->and(PolicyVersion::count())->toBe(31)
        ->and(PolicyVersion::query()->published()->count())->toBe(31)
        ->and(PolicyBundle::count())->toBe(1)
        ->and(PolicyBundle::sole()->versions()->count())->toBe(31)
        ->and(PaymentMethod::count())->toBe(4);
});

it('fills the home page copy without replacing what staff wrote', function () {
    $this->seed(CmsSeeder::class);
    $home = Page::firstWhere('slug', 'home');
    $home->update(['content' => [...$home->content, 'contact_heading' => 'Our own heading', 'about_heading' => '']]);

    $this->seed(CmsSeeder::class);
    $content = $home->refresh()->content;

    expect($content['contact_heading'])->toBe('Our own heading')
        ->and($content['about_heading'])->toBe('Executive mobility in Nairobi since 2023')
        ->and($content['why_choose_us'])->toHaveCount(4)
        ->and($content['why_choose_us'][0]['title'])->toBe('24/7 support')
        ->and($content['requirements'][2]['title'])->toBe('ID or passport');
});

it('seeds the client\'s car types and marquee brands', function () {
    $this->seed(CmsSeeder::class);

    expect(CarType::query()->ordered()->pluck('name')->all())
        ->toBe(['Executive Sedans', 'Executive SUVs', 'SUVs', 'Medium SUVs', 'Safari Landcruiser', 'Sports Cars', 'Economy'])
        ->and(Brand::query()->inMarquee()->pluck('name')->all())
        ->toBe(['Mercedes-Benz', 'BMW', 'Toyota', 'Nissan', 'Honda', 'Lexus']);
});

it('has no unresolved [CONFIRM] notes left in the policies', function () {
    $this->seed(CmsSeeder::class);

    expect(PolicyVersion::query()->where('body', 'like', '%[CONFIRM%')->count())->toBe(0)
        ->and(Policy::firstWhere('slug', 'booking-and-payment')->published->body)->toContain('euros and UAE dirhams');
});

it('fills empty settings and leaves TODO values and admin edits alone', function () {
    $settings = app(GeneralSettings::class);
    $settings->email = 'office@baines.test';
    $settings->whatsapp_templates = [...$settings->whatsapp_templates, 'general_enquiry' => 'Our own wording.'];
    $settings->save();

    $this->seed(CmsSeeder::class);
    $settings = app(GeneralSettings::class)->refresh();

    expect($settings->email)->toBe('office@baines.test')
        ->and($settings->whatsapp_templates['general_enquiry'])->toBe('Our own wording.')
        ->and($settings->whatsapp_templates['car_enquiry'])->toContain('{car}')->not->toContain('{car_name}')
        ->and($settings->whatsapp_number)->toBe('+254724422051')
        ->and($settings->socials)->toBe([
            'instagram' => 'https://www.instagram.com/bainesexecutives/',
            'tiktok' => 'https://www.tiktok.com/@bainesexecutives',
        ])
        ->and($settings->legal_name)->toBeNull()
        ->and($settings->opening_hours)->toBe([]);
});

it('publishes a new version when the pack text changes', function () {
    $policy = Policy::factory()->create(['slug' => 'mileage', 'title' => 'Mileage']);
    $policy->versions()->create(['version' => 1, 'body' => 'Older seed text.', 'published_at' => now()]);

    $this->seed(CmsSeeder::class);

    $policy->load('published');

    expect($policy->published->version)->toBe(2)
        ->and($policy->published->created_by)->toBeNull()
        ->and($policy->published->body)->toContain('250 km a day')
        ->and(PolicyBundle::count())->toBe(1);
});

it('never replaces text staff published or drafted', function () {
    $this->seed(CmsSeeder::class);
    $admin = User::factory()->staff()->create();
    $policies = app(PolicyService::class);

    $fuel = Policy::firstWhere('slug', 'fuel-policy');
    $policies->saveDraft($fuel, 'Our own fuel policy.', $admin);
    $policies->publish($fuel, $admin);

    $mileage = Policy::firstWhere('slug', 'mileage');
    $policies->saveDraft($mileage, 'Work in progress.', $admin);

    $this->seed(CmsSeeder::class);

    expect($fuel->published()->first()->body)->toBe('Our own fuel policy.')
        ->and($mileage->draft()->first()->body)->toBe('Work in progress.')
        ->and(PolicyVersion::count())->toBe(33)
        ->and(PolicyBundle::count())->toBe(2);
});

it('gives the starting roles their content permissions', function () {
    Storage::fake('media-public-local'); // FleetSeeder uploads the client's photos.
    $this->seed(DatabaseSeeder::class);

    $editor = User::factory()->staff()->create()->assignRole('content-editor');
    $ops = User::factory()->staff()->create()->assignRole('operations');

    expect($editor->can(Permission::PoliciesPublish))->toBeTrue()
        ->and($editor->can(Permission::PagesUpdate))->toBeTrue()
        ->and($editor->can(Permission::SettingsHomeUpdate))->toBeTrue()
        ->and($editor->can(Permission::ContactMessagesView))->toBeFalse()
        ->and($ops->can(Permission::ContactMessagesUpdate))->toBeTrue()
        ->and($ops->can(Permission::PaymentMethodsView))->toBeTrue()
        ->and($ops->can(Permission::PaymentMethodsUpdate))->toBeFalse()
        ->and($ops->can(Permission::PoliciesView))->toBeFalse();
});
