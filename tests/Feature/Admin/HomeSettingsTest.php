<?php

use App\Enums\MarqueePosition;
use App\Enums\Permission;
use App\Exceptions\Domain\MediaException;
use App\Http\Requests\Admin\Settings\UpdateHomeSettingsRequest;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Settings\HomeSettings;
use Illuminate\Http\UploadedFile;
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

function heroClip(User $uploader, string $name = 'clip.mp4'): Media
{
    // An MP4 header (ftyp + mdat) is enough for MIME sniffing; videos are stored as uploaded.
    $bytes = hex2bin('00000018667479706d703432000000006d70343269736f6d000000086d646174').str_repeat("\0", 512);

    return app(MediaService::class)->upload(UploadedFile::fake()->createWithContent($name, $bytes), 'site.hero_video', $uploader);
}

it('offers a hero video with guidance for both hero uploaders', function () {
    $this->actingAs(staffWith([Permission::SettingsHomeUpdate]))
        ->get(route('admin.settings.edit', 'home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('context.heroVideo', null)
            ->where('context.heroVideoLimits.key', 'site.hero_video')
            ->where('context.heroVideoLimits.mimes', ['mp4', 'webm'])
            ->where('context.heroVideoLimits.guidance.width', 1920)
            ->where('context.heroLimits.guidance.shape', 'Wide 16:9'));
});

it('saves a hero video, shows it on the home page and releases the old one', function () {
    $editor = staffWith([Permission::SettingsHomeUpdate, Permission::MediaUpload]);
    $first = heroClip($editor);
    $second = heroClip($editor, 'second.mp4');

    expect($first->mime_type)->toBe('video/mp4')->and($first->extension)->toBe('mp4');

    $this->actingAs($editor)->put(route('admin.settings.update', 'home'), homePayload(['hero_video_uuid' => $first->uuid]))->assertSessionHasNoErrors();
    $this->actingAs($editor)->put(route('admin.settings.update', 'home'), homePayload(['hero_video_uuid' => $second->uuid]))->assertSessionHasNoErrors();

    expect(app(HomeSettings::class)->refresh()->hero_video_uuid)->toBe($second->uuid)
        ->and(Media::withTrashed()->find($first->id)->trashed())->toBeTrue();

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('hero.video.uuid', $second->uuid)
        ->where('hero.video.mime', 'video/mp4')
        ->has('hero.video.urls.original'));

    $this->actingAs($editor)->put(route('admin.settings.update', 'home'), homePayload(['hero_video_uuid' => null]))->assertSessionHasNoErrors();
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('hero.video', null));
});

it('keeps images out of the video slot and videos out of the image slot', function () {
    $editor = staffWith([Permission::SettingsHomeUpdate, Permission::MediaUpload]);
    $photo = CatalogFixtures::photo($editor, 'site.hero');
    $clip = heroClip($editor);

    $this->actingAs($editor)
        ->put(route('admin.settings.update', 'home'), homePayload(['hero_video_uuid' => $photo->uuid, 'hero_media_uuid' => $clip->uuid]))
        ->assertSessionHasErrors(['hero_video_uuid', 'hero_media_uuid']);

    expect(fn () => app(MediaService::class)->upload(UploadedFile::fake()->image('still.jpg'), 'site.hero_video', $editor))
        ->toThrow(MediaException::class);
});
