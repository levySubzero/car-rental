<?php

use App\Enums\MarqueePosition;
use App\Enums\Permission;
use App\Http\Requests\Admin\Settings\UpdateHomeSettingsRequest;
use App\Settings\HomeSettings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => CatalogFixtures::setUp());

function homePayload(array $overrides = []): array
{
    return array_replace([
        'hero_media_uuid' => null,
        'headline' => 'Drive Nairobi in style',
        'subline' => 'Self-drive and chauffeur cars.',
        'primary_cta_label' => 'Rent a Car',
        'secondary_cta_label' => 'Enquire',
        'featured_car_count' => '8',
        'marquee_position' => 'below_hero',
        'sections' => array_map(
            fn (string $key) => ['key' => $key, 'visible' => $key !== 'trust_strip'],
            array_reverse(UpdateHomeSettingsRequest::SECTIONS),
        ),
        'trust_figures' => [['value' => '500+', 'label' => 'Happy clients']],
    ], $overrides);
}

it('opens the home tab with the hero context', function () {
    $this->actingAs(staffWith([Permission::SettingsHomeUpdate]))
        ->get(route('admin.settings.edit', 'home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.featured_car_count', 6)
            ->where('context.hero', null)
            ->where('context.heroLimits.key', 'site.hero'));
});

it('saves home settings with a new hero and releases the old one', function () {
    $editor = staffWith([Permission::SettingsHomeUpdate, Permission::MediaUpload]);
    $first = CatalogFixtures::photo($editor, 'site.hero');
    $second = CatalogFixtures::photo($editor, 'site.hero');

    $this->actingAs($editor)->put(route('admin.settings.update', 'home'), homePayload(['hero_media_uuid' => $first->uuid]))->assertSessionHasNoErrors();
    $this->actingAs($editor)->put(route('admin.settings.update', 'home'), homePayload(['hero_media_uuid' => $second->uuid]))->assertSessionHasNoErrors();

    $settings = app(HomeSettings::class);

    expect($settings->hero_media_uuid)->toBe($second->uuid)
        ->and($settings->featured_car_count)->toBe(8)
        ->and($settings->marquee_position)->toBe(MarqueePosition::BelowHero)
        ->and($settings->sections[0])->toBe(['key' => 'contact_cta', 'visible' => true])
        ->and($settings->sections[1])->toBe(['key' => 'trust_strip', 'visible' => false])
        ->and($first->fresh()->trashed())->toBeTrue()
        ->and($second->fresh()->status->value)->toBe('attached');
});

it('validates home settings', function () {
    $this->actingAs(staffWith([Permission::SettingsHomeUpdate]))
        ->put(route('admin.settings.update', 'home'), homePayload([
            'featured_car_count' => 20,
            'sections' => [['key' => 'about', 'visible' => true], ['key' => 'about', 'visible' => true]],
            'trust_figures' => array_fill(0, 7, ['value' => '1', 'label' => 'x']),
            'hero_media_uuid' => '00000000-0000-0000-0000-000000000000',
        ]))
        ->assertSessionHasErrors(['featured_car_count', 'sections', 'sections.1.key', 'trust_figures', 'hero_media_uuid']);
});
