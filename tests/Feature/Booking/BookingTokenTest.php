<?php

use App\Enums\BookingStatus;
use App\Http\Middleware\ResolveMediaUploader;
use App\Models\Booking;
use App\Services\Booking\BookingTokenService;
use Tests\Support\BookingFixtures;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->tokens = app(BookingTokenService::class);
});

it('stores only the hash and finds the booking by its plain token', function () {
    ['token' => $token, 'hash' => $hash] = $this->tokens->issue();
    $booking = Booking::factory()->create(['access_token_hash' => $hash]);

    expect(strlen($token))->toBe(64)
        ->and($hash)->toBe(hash('sha256', $token))
        ->and($this->tokens->find($token)?->is($booking))->toBeTrue()
        ->and($this->tokens->find(strrev($token)))->toBeNull()
        ->and($this->tokens->find('short'))->toBeNull();
});

it('rotates the token so the old link stops working', function () {
    ['token' => $old, 'hash' => $hash] = $this->tokens->issue();
    $booking = Booking::factory()->create(['access_token_hash' => $hash]);

    $new = $this->tokens->rotate($booking);

    expect($this->tokens->find($old))->toBeNull()
        ->and($this->tokens->find($new)?->is($booking))->toBeTrue();
});

it('lets a draft upload its documents with the token, and nothing else', function () {
    $token = str_repeat('a', 64);
    $booking = Booking::factory()->withToken($token)->create();
    $upload = fn (string $token, string $collection = 'booking.licence') => $this
        ->withHeaders(['Accept' => 'application/json', ResolveMediaUploader::TOKEN_HEADER => $token])
        ->post(route('media.store'), ['collection' => $collection, 'file' => MediaFixtures::pdf()]);

    $upload($token)->assertCreated()->assertJsonPath('visibility', 'private');
    $upload(str_repeat('b', 64))->assertForbidden();
    $upload($token, 'car.gallery')->assertForbidden();

    $booking->forceFill(['status' => BookingStatus::AwaitingPayment])->save();
    $upload($token)->assertForbidden();
});
