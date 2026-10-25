<?php

use App\Models\Media;
use App\Models\Service;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Settings\HomeSettings;
use Database\Seeders\CmsSeeder;
use Database\Seeders\SiteMediaSeeder;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    $this->seed(CmsSeeder::class);
    $this->staff = User::factory()->staff()->create();
});

it('adds the hero and a photo to every service, once', function () {
    $this->seed(SiteMediaSeeder::class);
    $this->seed(SiteMediaSeeder::class);

    $hero = app(HomeSettings::class)->refresh()->hero_media_uuid;

    expect($hero)->not->toBeNull()
        ->and(Media::firstWhere('uuid', $hero)->collection)->toBe('site.hero')
        ->and(Service::query()->has('image')->count())->toBe(6)
        ->and(Media::query()->where('collection', 'service.image')->count())->toBe(6)
        ->and(Media::query()->whereIn('collection', ['site.hero', 'service.image'])->where('processing', 'done')->count())->toBe(7);
});

it('leaves a hero or service photo staff already set', function () {
    $own = CatalogFixtures::photo($this->staff, 'service.image');
    $safaris = Service::firstWhere('slug', 'safaris');
    app(MediaService::class)->sync($safaris, 'service.image', [$own->uuid], actor: $this->staff);

    $home = app(HomeSettings::class);
    $hero = CatalogFixtures::photo($this->staff, 'site.hero');
    app(MediaService::class)->claim('site.hero', [$hero->uuid], actor: $this->staff);
    $home->hero_media_uuid = $hero->uuid;
    $home->save();

    $this->seed(SiteMediaSeeder::class);

    expect($safaris->image()->first()->uuid)->toBe($own->uuid)
        ->and(app(HomeSettings::class)->refresh()->hero_media_uuid)->toBe($hero->uuid)
        ->and(Service::query()->has('image')->count())->toBe(6);
});
