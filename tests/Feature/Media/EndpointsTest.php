<?php

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Support\Gadget;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    MediaFixtures::setUp();
    $this->gadget = Gadget::create(['name' => 'Range Rover']);
});

function uploadAs($test, $user, string $collection, $file = null)
{
    return $test->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('media.store'), ['collection' => $collection, 'file' => $file ?? MediaFixtures::jpeg()]);
}

describe('upload', function () {
    it('returns a MediaResource for permitted staff', function () {
        $response = uploadAs($this, staffWith(['media.upload', 'users.update']), 'gadget.photos')
            ->assertCreated()
            ->assertJsonPath('collection', 'gadget.photos')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('visibility', 'public')
            ->assertJsonStructure(['uuid', 'name', 'mime', 'size', 'width', 'height', 'urls' => ['original', 'thumb', 'card', 'full']])
            ->assertJsonMissingPath('path')
            ->assertJsonMissingPath('disk');

        expect($response->json('urls.thumb'))->toStartWith('http://localhost/media-files/gadget.photos/');
    });

    it('requires both media.upload and the collection permission', function (array $permissions) {
        uploadAs($this, staffWith($permissions), 'gadget.photos')->assertForbidden();
    })->with([
        'neither' => [[]],
        'only media.upload' => [['media.upload']],
        'only the collection permission' => [['users.update']],
    ]);

    it('rejects guests', function () {
        $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('media.store'), ['collection' => 'gadget.photos', 'file' => MediaFixtures::jpeg()])
            ->assertForbidden();
    });

    it('ignores staff who still need to set up required 2FA', function () {
        app(SecuritySettings::class)->fill(['require_two_factor' => true])->save();
        $user = staffWith(['media.upload', 'users.update']);
        $user->forceFill(['two_factor_confirmed_at' => null])->save();

        uploadAs($this, $user, 'gadget.photos')->assertForbidden();
    });

    it('does not let the super admin bypass skip required 2FA', function () {
        app(SecuritySettings::class)->fill(['require_two_factor' => true])->save();
        $admin = superAdmin();
        $admin->forceFill(['two_factor_confirmed_at' => null])->save();

        uploadAs($this, $admin, 'gadget.photos')->assertForbidden();
    });

    it('gives field errors for the wrong type, size or collection', function () {
        $user = staffWith(['media.upload', 'users.update']);

        uploadAs($this, $user, 'gadget.photos', MediaFixtures::disguisedScript())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'This file type is not allowed. Use JPG, PNG, WEBP']);

        uploadAs($this, $user, 'nope')->assertUnprocessable()->assertJsonValidationErrors('collection');
        $this->assertDatabaseCount('media', 0);
    });

    it('returns the service error as JSON when sniffing disagrees with validation', function () {
        uploadAs($this, staffWith(['media.upload', 'users.update']), 'gadget.photos', MediaFixtures::brokenJpeg())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This image could not be read. It may be damaged; try another file.')
            ->assertJsonValidationErrors(['file' => 'could not be read']);
    });

    it('never accepts uploads into system collections', function () {
        $collections = MediaFixtures::COLLECTIONS;
        $collections['gadget.voucher'] = ['owner' => 'gadget', 'visibility' => 'private', 'mimes' => ['pdf'], 'upload' => ['system' => true]];
        config()->set('media.collections', $collections);

        uploadAs($this, superAdmin(), 'gadget.voucher', MediaFixtures::pdf())->assertForbidden();
    });
});

describe('viewing', function () {
    beforeEach(function () {
        $this->uploader = staffWith(['media.upload', 'users.update']);
        $this->service = app(MediaService::class);
    });

    it('redirects public files to the disk URL with long cache headers', function () {
        $media = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->uploader)->fresh();

        $response = $this->get(route('media.show', [$media->uuid, 'card']))->assertRedirect();

        expect($response->headers->get('Location'))->toBe(Storage::disk('media-public-local')->url($media->conversions['card']))
            ->and($response->headers->get('Cache-Control'))->toContain('immutable');
    });

    it('streams public files when configured to', function () {
        config()->set('media.serve_public_via', 'stream');
        $media = $this->service->upload(MediaFixtures::png(), 'gadget.photos', $this->uploader);

        $this->get(route('media.show', $media->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('404s for unknown conversions and deleted files', function () {
        $media = $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->uploader);

        $this->get("/media/{$media->uuid}/huge")->assertNotFound();

        $media->delete();
        $this->get(route('media.show', $media->uuid))->assertNotFound();
    });

    it('guards private files and logs each authorized view', function () {
        $doc = $this->service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->uploader);
        $this->service->sync($this->gadget, 'gadget.docs', [$doc->uuid], system: true);

        $this->get(route('media.show', $doc->uuid))->assertForbidden();
        $this->actingAs(staffWith())->get(route('media.show', $doc->uuid))->assertForbidden();

        // users.view is the gadget owner's view permission.
        $viewer = staffWith(['users.view']);
        $response = $this->actingAs($viewer)->get(route('media.show', $doc->uuid))->assertRedirect();

        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('Location'))->toContain('/media/s/'.$doc->uuid);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'media', 'event' => 'viewed', 'subject_id' => $doc->id, 'causer_id' => $viewer->id]);

        // The signed URL streams without a session, until it expires.
        $signed = $response->headers->get('Location');
        auth()->logout();

        $this->get($signed)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');

        $this->travel(6)->minutes();
        $this->get($signed)->assertForbidden();
    });

    it('lets media.view-private open any private file', function () {
        $doc = $this->service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->uploader);

        $this->actingAs(staffWith(['media.view-private']))->get(route('media.show', $doc->uuid))->assertRedirect();
    });

    it('downloads with an attachment disposition and logs it', function () {
        $doc = $this->service->upload(MediaFixtures::pdf('Driving Licence (front).pdf'), 'gadget.docs', $this->uploader);

        $this->actingAs($this->uploader)->get(route('media.download', $doc->uuid))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="Driving Licence front.pdf"');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'media', 'event' => 'downloaded', 'subject_id' => $doc->id]);
    });

    it('rejects a tampered signed URL', function () {
        $doc = $this->service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->uploader);
        $other = $this->service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->uploader);

        $signed = URL::temporarySignedRoute('media.signed', now()->addMinutes(5), ['media' => $doc->uuid]);

        $this->get(str_replace($doc->uuid, $other->uuid, $signed))->assertForbidden();
    });
});

describe('managing', function () {
    beforeEach(function () {
        $this->editor = staffWith(['media.upload', 'users.update']);
        $this->service = app(MediaService::class);
        [$this->a, $this->b] = [
            $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->editor),
            $this->service->upload(MediaFixtures::jpeg(), 'gadget.photos', $this->editor),
        ];
    });

    it('updates alt text', function () {
        $this->actingAs($this->editor)
            ->patchJson(route('media.update', $this->a->uuid), ['alt' => 'Side view'])
            ->assertOk()
            ->assertJsonPath('alt', 'Side view');

        $this->actingAs(staffWith())->patchJson(route('media.update', $this->a->uuid), ['alt' => 'x'])->assertForbidden();
    });

    it('lets the uploader delete a pending file but not someone else', function () {
        $this->actingAs(staffWith(['media.upload', 'users.update']))->deleteJson(route('media.destroy', $this->a->uuid))->assertForbidden();

        $this->actingAs($this->editor)->deleteJson(route('media.destroy', $this->a->uuid))->assertNoContent();

        expect(Media::withTrashed()->find($this->a->id)->trashed())->toBeTrue();
    });

    it('lets the owner\'s editors delete attached files', function () {
        $this->service->sync($this->gadget, 'gadget.photos', [$this->a->uuid], system: true);

        $this->actingAs(staffWith(['users.update']))->deleteJson(route('media.destroy', $this->a->uuid))->assertNoContent();
    });

    it('reorders an owner\'s files', function () {
        $this->service->sync($this->gadget, 'gadget.photos', [$this->a->uuid, $this->b->uuid], system: true);

        $payload = ['collection' => 'gadget.photos', 'owner_type' => 'gadget', 'owner_id' => $this->gadget->id, 'uuids' => [$this->b->uuid, $this->a->uuid]];

        $this->actingAs(staffWith())->postJson(route('media.reorder'), $payload)->assertForbidden();
        $this->actingAs($this->editor)->postJson(route('media.reorder'), $payload)->assertNoContent();

        expect($this->gadget->mediaIn('gadget.photos')->pluck('uuid')->all())->toBe([$this->b->uuid, $this->a->uuid]);
    });

    it('refuses a reorder naming an owner type the collection does not use', function () {
        $this->actingAs(superAdmin())->postJson(route('media.reorder'), [
            'collection' => 'gadget.photos', 'owner_type' => 'user', 'owner_id' => $this->editor->id, 'uuids' => [$this->a->uuid],
        ])->assertNotFound();
    });

    it('keeps pending files pending until a form saves them', function () {
        expect($this->a->fresh()->status)->toBe(MediaStatus::Pending);
    });
});
