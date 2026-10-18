<?php

use App\Services\Media\MediaCollectionRegistry;
use App\Settings\MediaSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('media.collections', [
        'site.hero' => [
            'label' => 'Home page hero',
            'visibility' => 'public',
            'max_files' => 3,
            'max_kb' => 4096,
            'mimes' => ['jpg', 'png', 'webp'],
            'conversions' => ['thumb'],
            'upload' => ['permission' => 'settings.home.update'],
        ],
    ]);
});

function heroLimits(array $overrides = []): array
{
    return ['collections' => ['site.hero' => array_replace([
        'max_files' => 3,
        'max_kb' => 4096,
        'mimes' => ['jpg', 'png', 'webp'],
    ], $overrides)]];
}

it('ships the site.hero collection', function () {
    config()->set('media.collections', (require config_path('media.php'))['collections']);

    $hero = app(MediaCollectionRegistry::class)->get('site.hero');

    expect($hero->isStandalone())->toBeTrue()
        ->and($hero->uploadPermission)->toBe('settings.home.update');
});

it('shows each collection with its ceiling and effective limits', function () {
    app(MediaSettings::class)->fill(['collection_overrides' => ['site.hero' => ['max_files' => 1]]])->save();

    $this->actingAs(staffWith(['settings.media.update']))
        ->get(route('admin.settings.edit', 'media'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/edit')
            ->where('tabs', [['key' => 'media', 'label' => 'Uploads']])
            ->where('context.collections.0.key', 'site.hero')
            ->where('context.collections.0.max_files', 3)
            ->where('context.collections.0.effective.max_files', 1));
});

it('forbids the tab without permission', function () {
    $user = staffWith(['settings.general.update']);

    $this->actingAs($user)->get(route('admin.settings.edit', 'media'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.settings.update', 'media'), heroLimits())->assertForbidden();
});

it('stores only the values that tighten a ceiling', function () {
    $user = staffWith(['settings.media.update']);

    $this->actingAs($user)
        ->put(route('admin.settings.update', 'media'), heroLimits(['max_files' => '2', 'mimes' => ['webp', 'jpg']]))
        ->assertSessionHasNoErrors();

    expect(app(MediaSettings::class)->collection_overrides)
        ->toBe(['site.hero' => ['max_files' => 2, 'mimes' => ['jpg', 'webp']]])
        ->and(app(MediaCollectionRegistry::class)->get('site.hero')->maxFiles)->toBe(2);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'causer_id' => $user->id, 'description' => 'Uploads settings updated']);

    // Back at the ceiling: nothing to store.
    $this->actingAs($user)->put(route('admin.settings.update', 'media'), heroLimits())->assertSessionHasNoErrors();

    expect(app(MediaSettings::class)->collection_overrides)->toBe([]);
});

it('rejects limits above the ceiling or unknown file types', function () {
    $this->actingAs(staffWith(['settings.media.update']))
        ->put(route('admin.settings.update', 'media'), heroLimits([
            'max_files' => 4,
            'max_kb' => 99_999,
            'mimes' => ['jpg', 'exe'],
        ]))
        ->assertSessionHasErrors([
            'collections.site.hero.max_files',
            'collections.site.hero.max_kb',
            'collections.site.hero.mimes.1',
        ]);
});

it('requires at least one file type', function () {
    $this->actingAs(staffWith(['settings.media.update']))
        ->put(route('admin.settings.update', 'media'), heroLimits(['mimes' => []]))
        ->assertSessionHasErrors(['collections.site.hero.mimes']);
});
