<?php

use App\Enums\AvailabilityBlockType;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Exceptions\Domain\PaymentException;
use App\Models\Booking;
use App\Models\PaymentSubmission;
use App\Services\Payments\PaymentVerificationService;
use App\Support\Money;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));
    $this->service = app(PaymentVerificationService::class);
    $this->staff = staffWith([Permission::PaymentsView, Permission::PaymentsVerify, Permission::PaymentsReject]);

    $this->booking = Booking::factory()->awaitingPayment()->create(['status' => BookingStatus::PaymentSubmitted, 'expires_at' => null]);
    $this->booking->availabilityBlocks()->update(['expires_at' => null]);
    $this->submission = PaymentSubmission::factory()->for($this->booking)->create();
});

it('reserves the booking and firm-blocks the car', function () {
    $this->service->verify($this->submission, ' TJK1AB2CD3 ', Money::fromMajor('16588'), $this->staff);

    $booking = $this->booking->fresh();
    $submission = $this->submission->fresh();

    expect($booking->status)->toBe(BookingStatus::Reserved)
        ->and($booking->reservation_paid_minor)->toBe(1_658_800)
        ->and($booking->balance_minor)->toBe(4_976_200)
        ->and($booking->paid_at)->not->toBeNull()
        ->and($submission->status)->toBe(PaymentStatus::Verified)
        ->and($submission->reference)->toBe('TJK1AB2CD3')
        ->and($submission->verified_by)->toBe($this->staff->id)
        ->and($booking->availabilityBlocks()->sole()->type)->toBe(AvailabilityBlockType::Booked);

    expect(Activity::query()->where('log_name', 'payments')->sole()->getProperty('partial'))->toBeFalse();
});

it('moves an overpayment off the balance', function () {
    $this->service->verify($this->submission, 'REF', Money::fromMajor('20000'), $this->staff);

    expect($this->booking->fresh()->balance_minor)->toBe(4_635_000);
});

it('accepts a part payment only with the permission and a note', function () {
    expect(fn () => $this->service->verify($this->submission, 'REF', Money::fromMajor('10000'), $this->staff, 'Rest at handover'))
        ->toThrow(PaymentException::class, 'accept-partial');

    $senior = staffWith([Permission::PaymentsView, Permission::PaymentsVerify, Permission::PaymentsAcceptPartial]);

    expect(fn () => $this->service->verify($this->submission, 'REF', Money::fromMajor('10000'), $senior, '  '))
        ->toThrow(PaymentException::class, 'note');

    $this->service->verify($this->submission, 'REF', Money::fromMajor('10000'), $senior, 'Rest at handover');

    $booking = $this->booking->fresh();
    expect($booking->status)->toBe(BookingStatus::Reserved)
        ->and($booking->reservation_paid_minor)->toBe(1_000_000)
        ->and($booking->balance_minor)->toBe(5_635_000)
        ->and(Activity::query()->where('log_name', 'payments')->sole()->getProperty('partial'))->toBeTrue();
});

it('verifies a payment only once', function () {
    $this->service->verify($this->submission, 'REF', Money::fromMajor('16588'), $this->staff);

    expect(fn () => $this->service->verify($this->submission, 'REF', Money::fromMajor('16588'), $this->staff))
        ->toThrow(PaymentException::class, 'already been verified or rejected');
    expect(fn () => $this->service->reject($this->submission, 'Oops', $this->staff))
        ->toThrow(PaymentException::class);
});

it('rejects with a reason and gives the renter a fresh hold', function () {
    $this->service->reject($this->submission, 'No payment with this code.', $this->staff);

    $booking = $this->booking->fresh();
    expect($booking->status)->toBe(BookingStatus::PaymentRejected)
        ->and($booking->expires_at->toIso8601String())->toBe(now()->addMinutes(120)->toIso8601String())
        ->and($booking->availabilityBlocks()->sole()->expires_at->toIso8601String())->toBe(now()->addMinutes(120)->toIso8601String())
        ->and($this->submission->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($this->submission->fresh()->rejection_reason)->toBe('No payment with this code.');
});

it('verifies cash when it arrives, and never rejects it', function () {
    $booking = Booking::factory()->awaitingPayment()->create(['status' => BookingStatus::AwaitingCash, 'expires_at' => now()->addDay()]);
    $cash = PaymentSubmission::factory()->for($booking)->cash()->create();

    expect(fn () => $this->service->reject($cash, 'No', $this->staff))->toThrow(PaymentException::class, 'cash');

    $this->service->verify($cash, 'Receipt 0042', Money::fromMajor('16588'), $this->staff);

    expect($booking->fresh()->status)->toBe(BookingStatus::Reserved)
        ->and($booking->availabilityBlocks()->sole()->type)->toBe(AvailabilityBlockType::Booked);
});

it('refuses to reserve a lapsed cash hold whose dates were taken', function () {
    $booking = Booking::factory()->awaitingPayment()->create(['status' => BookingStatus::AwaitingCash]);
    $booking->availabilityBlocks()->update(['expires_at' => now()->subMinute()]);
    $cash = PaymentSubmission::factory()->for($booking)->cash()->create();

    Booking::factory()->awaitingPayment()->create([
        'car_id' => $booking->car_id,
        'pickup_at' => $booking->pickup_at,
        'return_at' => $booking->return_at,
    ]);

    expect(fn () => $this->service->verify($cash, 'Receipt', Money::fromMajor('16588'), $this->staff))
        ->toThrow(PaymentException::class, 'lapsed');
    expect($booking->fresh()->status)->toBe(BookingStatus::AwaitingCash);
});
