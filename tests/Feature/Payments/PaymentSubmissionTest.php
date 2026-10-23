<?php

use App\Enums\AvailabilityBlockType;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Http\Middleware\ResolveMediaUploader;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Services\Booking\BookingService;
use App\Settings\PricingSettings;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;
use Tests\Support\MediaFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));

    $this->methods = collect([
        ['code' => PaymentMethodCode::Mpesa, 'name' => 'M-Pesa', 'requires_transaction_message' => true],
        ['code' => PaymentMethodCode::Bank, 'name' => 'Bank transfer', 'requires_transaction_message' => true, 'requires_proof' => true],
        ['code' => PaymentMethodCode::Card, 'name' => 'Card', 'requires_transaction_message' => true],
        ['code' => PaymentMethodCode::Cash, 'name' => 'Cash', 'requires_transaction_message' => false],
    ])->map(fn (array $method) => PaymentMethod::factory()->create($method));

    $this->token = str_repeat('p', 64);
    $this->booking = Booking::factory()->awaitingPayment()->withToken($this->token)->create();
});

function uploadProof(string $token): string
{
    $uuid = test()
        ->withHeaders(['Accept' => 'application/json', ResolveMediaUploader::TOKEN_HEADER => $token])
        ->post(route('media.store'), ['collection' => 'booking.payment_proof', 'file' => MediaFixtures::pdf()])
        ->assertCreated()
        ->json('uuid');

    test()->flushHeaders();

    return $uuid;
}

function pay(array $input): TestResponse
{
    return test()->post(route('bookings.payment', test()->token), $input);
}

it('shows the methods while the booking waits for payment', function () {
    $this->get(route('bookings.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/booking/status')
            ->has('payment_methods', 4)
            ->where('payment_methods.0.code', 'mpesa')
            ->has('payment_methods.0.instructions_html')
            ->missing('payment_methods.0.metadata')
            ->where('payment', null));
});

it('takes an M-Pesa message and keeps the car until staff check it', function () {
    pay(['method' => 'mpesa', 'transaction_message' => 'TJK1AB2CD3 Confirmed. Ksh16,588.00 sent.'])->assertSessionHasNoErrors();

    $booking = $this->booking->fresh();
    $submission = $booking->payments()->sole();

    expect($booking->status)->toBe(BookingStatus::PaymentSubmitted)
        ->and($booking->expires_at)->toBeNull()
        ->and($submission->status)->toBe(PaymentStatus::Pending)
        ->and($submission->transaction_message)->toContain('TJK1AB2CD3')
        ->and($booking->availabilityBlocks()->sole()->expires_at)->toBeNull();

    // A held car with no expiry still blocks other renters, and the sweep leaves it alone.
    $this->travel(3)->days();
    app(BookingService::class)->expireStale(24);
    expect($booking->fresh()->status)->toBe(BookingStatus::PaymentSubmitted)
        ->and(Booking::query()->whereKey($booking->id)->first()->availabilityBlocks()->active()->exists())->toBeTrue();
});

it('attaches the proof uploaded with the booking token', function () {
    $uuid = uploadProof($this->token);

    pay(['method' => 'bank', 'transaction_message' => 'Transfer ref FT2610', 'proof' => $uuid])->assertSessionHasNoErrors();

    $submission = PaymentSubmission::query()->sole();
    expect($submission->proof?->uuid)->toBe($uuid)
        ->and($submission->proof?->mediable_id)->toBe($this->booking->id);
});

it('asks for what each method needs', function (array $input, string $field) {
    pay($input)->assertSessionHasErrors($field);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
})->with([
    'no method' => [[], 'method'],
    'unknown method' => [['method' => 'bitcoin'], 'method'],
    'M-Pesa without a message' => [['method' => 'mpesa'], 'transaction_message'],
    'bank without a proof' => [['method' => 'bank', 'transaction_message' => 'FT2610'], 'proof'],
    'a proof that is not a file' => [['method' => 'mpesa', 'transaction_message' => 'x', 'proof' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'], 'proof'],
]);

it('refuses a method staff have switched off', function () {
    $this->methods->first()->forceFill(['is_active' => false])->save();

    pay(['method' => 'mpesa', 'transaction_message' => 'x'])->assertSessionHasErrors('method');

    $this->get(route('bookings.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->has('payment_methods', 3));
});

it('holds the car for cash for cash_hold_hours', function () {
    pay(['method' => 'cash', 'transaction_message' => 'ignored'])->assertSessionHasNoErrors();

    $booking = $this->booking->fresh();
    expect($booking->status)->toBe(BookingStatus::AwaitingCash)
        ->and($booking->expires_at->toIso8601String())->toBe(now()->addHours(24)->toIso8601String())
        ->and($booking->payments()->sole()->transaction_message)->toBeNull();

    $this->travel(25)->hours();
    app(BookingService::class)->expireStale(24);

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($booking->availabilityBlocks()->count())->toBe(0);
});

it('follows the cash hold setting', function () {
    $settings = app(PricingSettings::class);
    $settings->cash_hold_hours = 6;
    $settings->save();

    pay(['method' => 'cash'])->assertSessionHasNoErrors();

    expect($this->booking->fresh()->expires_at->toIso8601String())->toBe(now()->addHours(6)->toIso8601String());
});

it('takes one payment at a time and none once reserved', function () {
    pay(['method' => 'mpesa', 'transaction_message' => 'first'])->assertSessionHasNoErrors();
    pay(['method' => 'mpesa', 'transaction_message' => 'second']);

    expect(PaymentSubmission::query()->count())->toBe(1);

    $this->booking->forceFill(['status' => BookingStatus::Reserved])->save();
    pay(['method' => 'mpesa', 'transaction_message' => 'third']);

    expect(PaymentSubmission::query()->count())->toBe(1);
});

it('lets the renter pay again after a rejection, by cash too', function () {
    PaymentSubmission::factory()->for($this->booking)->rejected('Amount does not match.')->create();
    $this->booking->forceFill(['status' => BookingStatus::PaymentRejected])->save();

    $this->get(route('bookings.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment.status', 'rejected')
            ->where('payment.rejection_reason', 'Amount does not match.')
            ->has('payment_methods', 4));

    pay(['method' => 'cash'])->assertSessionHasNoErrors();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingCash)
        ->and($this->booking->payments()->count())->toBe(2);
});

it('takes a payment proof by token only while the booking waits for payment', function () {
    $upload = fn (string $collection) => $this
        ->withHeaders(['Accept' => 'application/json', ResolveMediaUploader::TOKEN_HEADER => $this->token])
        ->post(route('media.store'), ['collection' => $collection, 'file' => MediaFixtures::pdf()]);

    $upload('booking.payment_proof')->assertCreated();
    // KYC documents are fixed once the booking is submitted.
    $upload('booking.licence')->assertForbidden();

    $this->booking->forceFill(['status' => BookingStatus::PaymentSubmitted])->save();
    $upload('booking.payment_proof')->assertForbidden();

    $this->booking->forceFill(['status' => BookingStatus::PaymentRejected])->save();
    $upload('booking.payment_proof')->assertCreated();
});

it('expires an unanswered rejection when its new hold runs out', function () {
    $this->booking->forceFill(['status' => BookingStatus::PaymentRejected, 'expires_at' => now()->addHours(2)])->save();
    $this->booking->availabilityBlocks()->update(['expires_at' => now()->addHours(2)]);

    $this->travel(3)->hours();
    app(BookingService::class)->expireStale(24);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($this->booking->availabilityBlocks()->where('type', AvailabilityBlockType::Hold)->count())->toBe(0);
});
