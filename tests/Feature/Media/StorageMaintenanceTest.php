<?php

use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Gadget;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    MediaFixtures::setUp();
    $this->staff = User::factory()->staff()->create();
    $this->service = app(MediaService::class);
});

describe('media:cleanup', function () {
    it('removes expired pending uploads and keeps everything else', function () {
        $expired = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);
        $fresh = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);
        $attached = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);
        $this->service->sync(Gadget::create(['name' => 'Kept']), 'gadget.photos', [$attached->uuid]);

        $expired->update(['expires_at' => now()->subMinute()]);
        $disk = Storage::disk('media-public-local');

        $this->artisan('media:cleanup --dry-run')->expectsOutputToContain('1 would be removed')->assertSuccessful();
        expect(Media::find($expired->id))->not->toBeNull();

        $this->artisan('media:cleanup')->assertSuccessful();

        expect(Media::withTrashed()->find($expired->id))->toBeNull()
            ->and($disk->exists($expired->path))->toBeFalse()
            ->and($disk->directories(dirname($expired->path, 2)))->not->toContain(dirname($expired->path))
            ->and($disk->exists($fresh->path))->toBeTrue()
            ->and($disk->exists($attached->path))->toBeTrue();
    });

    it('purges deleted files after the retention window, with their conversions', function () {
        $media = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff)->fresh();
        $this->service->delete($media, $this->staff);
        $disk = Storage::disk('media-public-local');

        $this->artisan('media:cleanup')->assertSuccessful();
        expect($disk->exists($media->path))->toBeTrue();

        $this->travel(8)->days();
        $this->artisan('media:cleanup')->assertSuccessful();

        expect(Media::withTrashed()->find($media->id))->toBeNull()
            ->and($disk->exists($media->path))->toBeFalse()
            ->and($disk->exists($media->conversions['thumb']))->toBeFalse();
    });

    it('is scheduled hourly', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('media:cleanup')->assertSuccessful();
    });
});

describe('disk switch and media:migrate-disk', function () {
    beforeEach(function () {
        Storage::fake('media-public-s3', ['throw' => true]);
    });

    it('writes new uploads to the new disk while old files stay readable', function () {
        $old = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);

        // What changing MEDIA_PUBLIC_DISK in .env does.
        config()->set('media.disks.public', 'media-public-s3');
        $new = app(MediaService::class)->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);

        expect($old->fresh()->disk)->toBe('media-public-local')
            ->and($new->disk)->toBe('media-public-s3');

        Storage::disk('media-public-s3')->assertExists($new->path);
        $this->get(route('media.show', $old->uuid))->assertRedirect(Storage::disk('media-public-local')->url($old->path));
    });

    it('moves files and conversions, verifying each copy', function () {
        $media = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff)->fresh();
        $other = $this->service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->staff);

        $this->artisan('media:migrate-disk media-public-local media-public-s3 --dry-run')
            ->expectsOutputToContain('1 file(s) would move')
            ->assertSuccessful();
        expect($media->fresh()->disk)->toBe('media-public-local');

        $this->artisan('media:migrate-disk media-public-local media-public-s3')
            ->expectsOutputToContain('1 file(s) moved, 0 failed')
            ->assertSuccessful();

        expect($media->fresh()->disk)->toBe('media-public-s3')
            ->and($other->fresh()->disk)->toBe('media-private-local');

        Storage::disk('media-public-s3')->assertExists([$media->path, $media->conversions['card']]);
        Storage::disk('media-public-local')->assertMissing($media->path);
    });

    it('can keep the source and limit the run to one collection', function () {
        $media = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);
        $hero = $this->service->upload(MediaFixtures::jpeg(), 'site.hero', $this->staff);

        $this->artisan('media:migrate-disk media-public-local media-public-s3 --collection=gadget.photos --keep-source')->assertSuccessful();

        expect($media->fresh()->disk)->toBe('media-public-s3')
            ->and($hero->fresh()->disk)->toBe('media-public-local');
        Storage::disk('media-public-local')->assertExists($media->path);
    });

    it('reports a file whose copy does not match its checksum and leaves it in place', function () {
        $media = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->staff);
        Storage::disk('media-public-local')->put($media->path, 'tampered');

        $this->artisan('media:migrate-disk media-public-local media-public-s3')
            ->expectsOutputToContain('0 file(s) moved, 1 failed')
            ->assertFailed();

        expect($media->fresh()->disk)->toBe('media-public-local');
        Storage::disk('media-public-s3')->assertMissing($media->path);
    });

    it('refuses to move files between public and private disks, or to unknown disks', function () {
        $this->artisan('media:migrate-disk media-private-local media-public-s3')
            ->expectsOutputToContain('between public and private')
            ->assertFailed();

        $this->artisan('media:migrate-disk media-public-local nope')
            ->expectsOutputToContain('Unknown disk [nope]')
            ->assertFailed();
    });
});
