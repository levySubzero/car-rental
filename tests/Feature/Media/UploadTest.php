<?php

use App\Enums\MediaProcessing;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Exceptions\Domain\MediaException;
use App\Jobs\ProcessMediaJob;
use App\Models\User;
use App\Services\Media\ImageDriver;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    MediaFixtures::setUp();
    $this->uploader = User::factory()->staff()->create();
    $this->media = app(MediaService::class);
});

it('stores a public image as a pending upload on the public disk', function () {
    Queue::fake();

    $media = $this->media->upload(MediaFixtures::jpeg('My Car.JPG'), 'gadget.photos', $this->uploader);

    expect($media->status)->toBe(MediaStatus::Pending)
        ->and($media->visibility)->toBe(MediaVisibility::Public)
        ->and($media->disk)->toBe('media-public-local')
        ->and($media->original_name)->toBe('My Car.JPG')
        ->and($media->extension)->toBe('jpg')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and([$media->width, $media->height])->toBe([1200, 600])
        ->and($media->processing)->toBe(MediaProcessing::Queued)
        ->and($media->expires_at)->not->toBeNull()
        ->and($media->wasUploadedBy($this->uploader))->toBeTrue()
        ->and($media->path)->toStartWith('gadget.photos/'.now()->format('Y/m')."/{$media->uuid}/")
        ->and($media->file_name)->not->toContain('My Car');

    $stored = Storage::disk('media-public-local')->get($media->path);

    expect($media->checksum_sha256)->toBe(hash('sha256', $stored))
        ->and($media->size_bytes)->toBe(strlen($stored));

    Queue::assertPushed(ProcessMediaJob::class);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'media', 'event' => 'uploaded', 'subject_id' => $media->id]);
});

it('stores private collections on the private disk and skips conversions for PDFs', function () {
    $media = $this->media->upload(MediaFixtures::pdf(), 'gadget.docs', $this->uploader);

    expect($media->visibility)->toBe(MediaVisibility::Private)
        ->and($media->disk)->toBe('media-private-local')
        ->and($media->extension)->toBe('pdf')
        ->and($media->processing)->toBe(MediaProcessing::Skipped)
        ->and($media->width)->toBeNull();

    Storage::disk('media-private-local')->assertExists($media->path);
    Storage::disk('media-public-local')->assertMissing($media->path);
});

it('strips EXIF (including GPS) and applies the orientation before storing', function () {
    // Orientation 6: the camera was turned; the upright image is portrait.
    $media = $this->media->upload(MediaFixtures::jpeg(width: 400, height: 200, orientation: 6), 'gadget.photos', $this->uploader);

    $stored = Storage::disk('media-public-local')->get($media->path);

    expect(MediaFixtures::hasExif($stored))->toBeFalse()
        ->and([$media->width, $media->height])->toBe([200, 400])
        ->and($media->metadata['exif_stripped'])->toBeTrue();
});

it('keeps PNGs as PNG', function () {
    $media = $this->media->upload(MediaFixtures::png(), 'gadget.photos', $this->uploader);

    expect($media->extension)->toBe('png')->and($media->mime_type)->toBe('image/png');
});

it('takes the extension from the sniffed type, not the file name', function () {
    $media = $this->media->upload(MediaFixtures::png('looks-like.jpg'), 'gadget.photos', $this->uploader);

    expect($media->extension)->toBe('png')->and($media->file_name)->toEndWith('.png');
});

it('rejects files whose bytes are not an allowed type', function ($file) {
    expect(fn () => $this->media->upload($file(), 'gadget.photos', $this->uploader))
        ->toThrow(MediaException::class, 'This file type is not allowed. Use JPG, PNG, WEBP');

    expect(Storage::disk('media-public-local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('media', 0);
})->with([
    'php script named .jpg' => fn () => fn () => MediaFixtures::disguisedScript(),
    'pdf in an image collection' => fn () => fn () => MediaFixtures::pdf('scan.jpg'),
]);

it('rejects images over the pixel ceiling before decoding them (M12.1)', function () {
    config(['media.max_pixels' => 1200 * 600 - 1]);

    $this->media->upload(MediaFixtures::jpeg(width: 1200, height: 600), 'gadget.photos', $this->uploader);
})->throws(MediaException::class, 'too large to process');

it('rejects damaged images', function () {
    $this->media->upload(MediaFixtures::brokenJpeg(), 'gadget.photos', $this->uploader);
})->throws(MediaException::class, 'could not be read');

it('rejects files over the collection limit', function () {
    $collections = MediaFixtures::COLLECTIONS;
    $collections['gadget.photos']['max_kb'] = 1;
    config()->set('media.collections', $collections);

    app(MediaService::class)->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->uploader);
})->throws(MediaException::class, 'The limit is 1 KB.');

it('converts HEIC to JPEG when the image driver supports it', function () {
    $imagick = new Imagick;
    $imagick->newImage(300, 200, 'red');
    $imagick->setImageFormat('heic');

    $media = $this->media->upload(MediaFixtures::file('IMG_0001.HEIC', $imagick->getImageBlob()), 'gadget.photos', $this->uploader);

    expect($media->extension)->toBe('jpg')->and($media->mime_type)->toBe('image/jpeg');
})->skip(fn () => ! app(ImageDriver::class)->supports('heic'), 'HEIC needs Imagick with libheif');

it('writes the conversions and never upscales', function () {
    $media = $this->media->upload(MediaFixtures::jpeg(width: 600, height: 300), 'gadget.photos', $this->uploader)->fresh();

    expect($media->processing)->toBe(MediaProcessing::Done)
        ->and(array_keys($media->conversions))->toBe(['thumb', 'card']);

    $disk = Storage::disk('media-public-local');
    $thumb = getimagesizefromstring($disk->get($media->conversions['thumb']));
    $card = getimagesizefromstring($disk->get($media->conversions['card']));

    expect($thumb[0])->toBe(320)->and($thumb['mime'])->toBe('image/webp')
        // card is 800 wide, but the original is only 600.
        ->and($card[0])->toBe(600);
});

it('marks conversions failed and keeps the original when processing breaks', function () {
    Queue::fake();
    $media = $this->media->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->uploader);

    // The original disappeared from the disk.
    Storage::disk('media-public-local')->put($media->path, 'not an image');

    $job = new ProcessMediaJob($media);

    expect(fn () => $job->handle($this->media))->toThrow(MediaException::class);

    $job->failed(new RuntimeException('boom'));

    expect($media->fresh()->processing)->toBe(MediaProcessing::Failed);
});
