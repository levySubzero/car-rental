<?php

use App\Http\Middleware\ResolveMediaUploader;
use App\Models\Media;
use App\Services\Media\Contracts\TokenUploaderResolver;
use App\Services\Media\MediaService;
use Tests\Support\FakeTokenResolver;
use Tests\Support\Gadget;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    MediaFixtures::setUp();
    $this->mine = Gadget::create(['name' => 'Booking A']);
    $this->theirs = Gadget::create(['name' => 'Booking B']);

    app()->instance(TokenUploaderResolver::class, new FakeTokenResolver([
        'token-a' => $this->mine,
        'token-b' => $this->theirs,
    ]));
});

function tokenRequest($test, string $token)
{
    return $test->withHeaders(['Accept' => 'application/json', ResolveMediaUploader::TOKEN_HEADER => $token]);
}

it('accepts uploads into token collections for the token owner', function () {
    $response = tokenRequest($this, 'token-a')
        ->post(route('media.store'), ['collection' => 'gadget.docs', 'file' => MediaFixtures::pdf()])
        ->assertCreated()
        ->assertJsonPath('visibility', 'private')
        ->assertJsonStructure(['urls' => ['view', 'download']]);

    $media = Media::firstWhere('uuid', $response->json('uuid'));

    expect($media->uploaded_by_type)->toBe('gadget')
        ->and($media->uploaded_by_id)->toBe($this->mine->id);
});

it('rejects collections without token uploads, and unknown tokens', function () {
    tokenRequest($this, 'token-a')
        ->post(route('media.store'), ['collection' => 'gadget.photos', 'file' => MediaFixtures::jpeg()])
        ->assertForbidden();

    tokenRequest($this, 'nope')
        ->post(route('media.store'), ['collection' => 'gadget.docs', 'file' => MediaFixtures::pdf()])
        ->assertForbidden();
});

it('rejects every token by default until bookings exist', function () {
    app()->forgetInstance(TokenUploaderResolver::class);

    tokenRequest($this, 'token-a')
        ->post(route('media.store'), ['collection' => 'gadget.docs', 'file' => MediaFixtures::pdf()])
        ->assertForbidden();
});

it('lets a token see and delete only its own files', function () {
    $service = app(MediaService::class);
    $mine = $service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->mine);
    $theirs = $service->upload(MediaFixtures::pdf(), 'gadget.docs', $this->theirs);

    tokenRequest($this, 'token-a')->get(route('media.show', $mine->uuid))->assertRedirect();
    tokenRequest($this, 'token-a')->get(route('media.show', $theirs->uuid))->assertForbidden();
    tokenRequest($this, 'token-a')->get(route('media.download', $theirs->uuid))->assertForbidden();
    tokenRequest($this, 'token-a')->deleteJson(route('media.destroy', $theirs->uuid))->assertForbidden();
    tokenRequest($this, 'token-a')->deleteJson(route('media.destroy', $mine->uuid))->assertNoContent();

    // A file attached to the token owner stays visible to it.
    $attached = $service->upload(MediaFixtures::pdf(), 'gadget.docs', staffWith());
    $service->sync($this->mine, 'gadget.docs', [$attached->uuid]);

    tokenRequest($this, 'token-a')->get(route('media.show', $attached->uuid))->assertRedirect();
    tokenRequest($this, 'token-a')->patchJson(route('media.update', $attached->uuid), ['alt' => 'x'])->assertForbidden();
});

it('rate limits token uploads', function () {
    foreach (range(1, 30) as $attempt) {
        tokenRequest($this, 'token-a')->post(route('media.store'), ['collection' => 'gadget.docs', 'file' => MediaFixtures::disguisedScript()]);
    }

    tokenRequest($this, 'token-a')
        ->post(route('media.store'), ['collection' => 'gadget.docs', 'file' => MediaFixtures::pdf()])
        ->assertTooManyRequests();
});
