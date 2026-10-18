<?php

use App\Enums\MediaStatus;
use App\Exceptions\Domain\MediaException;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Support\Rules\MaxMediaForCollection;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Gadget;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    MediaFixtures::setUp();
    $this->staff = User::factory()->staff()->create();
    $this->gadget = Gadget::create(['name' => 'Range Rover']);
    $this->media = app(MediaService::class);
    $this->upload = fn (?User $by = null) => $this->media->upload(MediaFixtures::jpeg(), 'gadget.photos', $by ?? $this->staff);
});

it('attaches pending uploads in order and sets the cover', function () {
    [$a, $b, $c] = [($this->upload)(), ($this->upload)(), ($this->upload)()];

    $files = $this->media->sync($this->gadget, 'gadget.photos', [$c->uuid, $a->uuid, $b->uuid], cover: $a->uuid, actor: $this->staff);

    expect($files->pluck('uuid')->all())->toBe([$c->uuid, $a->uuid, $b->uuid])
        ->and($files->every(fn (Media $m) => $m->status === MediaStatus::Attached && $m->expires_at === null))->toBeTrue()
        ->and($files->firstWhere('is_primary', true)->uuid)->toBe($a->uuid)
        ->and($this->gadget->mediaIn('gadget.photos')->pluck('uuid')->all())->toBe([$c->uuid, $a->uuid, $b->uuid])
        ->and($this->gadget->primaryMedia('gadget.photos')->first()->uuid)->toBe($a->uuid);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'media', 'event' => 'synced', 'subject_type' => 'gadget']);
});

it('makes the first file the cover by default', function () {
    [$a, $b] = [($this->upload)(), ($this->upload)()];

    $this->media->sync($this->gadget, 'gadget.photos', [$b->uuid, $a->uuid]);

    expect($this->gadget->primaryMedia('gadget.photos')->first()->uuid)->toBe($b->uuid);
});

it('deletes files that are left out', function () {
    [$a, $b] = [($this->upload)(), ($this->upload)()];
    $this->media->sync($this->gadget, 'gadget.photos', [$a->uuid, $b->uuid]);

    $this->media->sync($this->gadget, 'gadget.photos', [$b->uuid]);

    expect($this->gadget->mediaIn('gadget.photos')->pluck('uuid')->all())->toBe([$b->uuid])
        ->and(Media::withTrashed()->firstWhere('uuid', $a->uuid)->trashed())->toBeTrue();
});

it('enforces the collection maximum', function () {
    $uuids = collect(range(1, 4))->map(fn () => ($this->upload)()->uuid)->all();

    $this->media->sync($this->gadget, 'gadget.photos', $uuids);
})->throws(MediaException::class, 'at most 3');

it('refuses another uploader\'s pending file', function () {
    $other = ($this->upload)(User::factory()->staff()->create());

    $this->media->sync($this->gadget, 'gadget.photos', [$other->uuid], actor: $this->staff);
})->throws(MediaException::class, 'not available');

it('refuses a file attached to another owner', function () {
    $file = ($this->upload)();
    $this->media->sync(Gadget::create(['name' => 'Other']), 'gadget.photos', [$file->uuid]);

    $this->media->sync($this->gadget, 'gadget.photos', [$file->uuid]);
})->throws(MediaException::class, 'not available');

it('refuses files from another collection or the wrong owner type', function () {
    $file = ($this->upload)();

    expect(fn () => $this->media->sync($this->gadget, 'gadget.docs', [$file->uuid]))->toThrow(MediaException::class, 'not available')
        ->and(fn () => $this->media->sync($this->staff, 'gadget.photos', [$file->uuid]))->toThrow(MediaException::class, 'cannot be attached here')
        ->and(fn () => $this->media->sync(null, 'gadget.photos', [$file->uuid]))->toThrow(MediaException::class, 'cannot be attached here');
});

it('claims files for a standalone collection and replaces the previous one', function () {
    $first = $this->media->upload(MediaFixtures::jpeg(), 'site.hero', $this->staff);
    $this->media->claim('site.hero', [$first->uuid], $this->staff);

    expect($first->fresh()->status)->toBe(MediaStatus::Attached)->and($first->fresh()->mediable_type)->toBeNull();

    $second = $this->media->upload(MediaFixtures::jpeg(), 'site.hero', $this->staff);
    $this->media->claim('site.hero', [$second->uuid], $this->staff);

    expect($first->fresh()->trashed())->toBeTrue()
        ->and($second->fresh()->status)->toBe(MediaStatus::Attached);
});

it('reorders exactly the current files', function () {
    [$a, $b] = [($this->upload)(), ($this->upload)()];
    $this->media->sync($this->gadget, 'gadget.photos', [$a->uuid, $b->uuid]);

    $this->media->reorder($this->gadget, 'gadget.photos', [$b->uuid, $a->uuid], $this->staff);

    expect($this->gadget->mediaIn('gadget.photos')->pluck('uuid')->all())->toBe([$b->uuid, $a->uuid])
        ->and(fn () => $this->media->reorder($this->gadget, 'gadget.photos', [$a->uuid]))->toThrow(MediaException::class);
});

it('appends with attach and passes the cover on when the cover is deleted', function () {
    [$a, $b] = [($this->upload)(), ($this->upload)()];
    $this->media->sync($this->gadget, 'gadget.photos', [$a->uuid]);
    $this->media->attach($this->gadget, 'gadget.photos', $b->uuid);

    expect($this->gadget->mediaIn('gadget.photos')->pluck('uuid')->all())->toBe([$a->uuid, $b->uuid]);

    $this->media->delete($a->fresh(), $this->staff);

    expect($b->fresh()->is_primary)->toBeTrue();
});

it('soft-deletes an owner\'s files with it', function () {
    $file = ($this->upload)();
    $this->media->sync($this->gadget, 'gadget.photos', [$file->uuid]);

    $this->gadget->delete();

    expect(Media::withTrashed()->find($file->id)->trashed())->toBeTrue();
});

it('updates only alt text and caption', function () {
    $file = ($this->upload)();

    $this->media->updateMetadata($file, ['alt' => '  Front three-quarter view ', 'caption' => ''], $this->staff);

    expect($file->fresh()->metadata->getArrayCopy())->toBe(['exif_stripped' => true, 'alt' => 'Front three-quarter view', 'caption' => null]);
});

it('validates uuids with the media rules', function () {
    $this->actingAs($this->staff);
    [$mine, $theirs] = [($this->upload)(), ($this->upload)(User::factory()->staff()->create())];

    $rules = fn () => [
        'gallery' => ['array', new MaxMediaForCollection('gadget.photos')],
        'gallery.*' => ['uuid', new PendingOrOwnedMedia('gadget.photos', $this->gadget)],
    ];

    expect(Validator::make(['gallery' => [$mine->uuid]], $rules())->passes())->toBeTrue()
        ->and(Validator::make(['gallery' => [$theirs->uuid]], $rules())->errors()->keys())->toBe(['gallery.0'])
        ->and(Validator::make(['gallery' => [fake()->uuid()]], $rules())->fails())->toBeTrue()
        ->and(Validator::make(['gallery' => array_fill(0, 4, $mine->uuid)], $rules())->passes())->toBeTrue()
        ->and(Validator::make(['gallery' => [$mine->uuid, $theirs->uuid, fake()->uuid(), fake()->uuid()]], $rules())->errors()->has('gallery'))->toBeTrue();
});
