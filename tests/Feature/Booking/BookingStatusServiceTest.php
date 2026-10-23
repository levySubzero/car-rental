<?php

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Exceptions\Domain\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Services\Booking\BookingStatusService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(BookingStatusService::class);
});

it('moves the status, logs one booking-status row and fires the event', function () {
    Event::fake([BookingStatusChanged::class]);
    $booking = Booking::factory()->create();
    $staff = staffWith();

    $this->service->transition($booking, BookingStatus::AwaitingPayment, 'renter');
    $this->service->transition($booking, BookingStatus::Expired, $staff, 'Not paid');

    $timeline = array_map(fn (array $row) => array_diff_key($row, ['at' => true]), $this->service->timeline($booking));

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($timeline)->toBe([
            ['from' => 'draft', 'to' => 'awaiting_payment', 'reason' => null, 'actor' => 'renter', 'causer' => null],
            ['from' => 'awaiting_payment', 'to' => 'expired', 'reason' => 'Not paid', 'actor' => 'staff', 'causer' => $staff->name],
        ]);

    Event::assertDispatchedTimes(BookingStatusChanged::class, 2);
    Event::assertDispatched(fn (BookingStatusChanged $event) => $event->from === BookingStatus::AwaitingPayment && $event->to === BookingStatus::Expired);
});

it('refuses an edge outside the map and changes nothing', function () {
    $booking = Booking::factory()->create();

    expect(fn () => $this->service->transition($booking, BookingStatus::Reserved, 'system'))
        ->toThrow(BookingException::class, 'from Draft to Reserved');

    expect($booking->fresh()->status)->toBe(BookingStatus::Draft)
        ->and($this->service->timeline($booking))->toBe([]);
});

it('frees the hold when a booking expires', function () {
    $booking = Booking::factory()->awaitingPayment()->create();
    AvailabilityBlock::factory()->hold($booking)->create(['car_id' => $booking->car_id]);

    $this->service->transition($booking, BookingStatus::Expired, 'system');

    expect(AvailabilityBlock::count())->toBe(0);
});

it('reads the timeline through the subject and log name index', function () {
    $indexes = collect(Schema::getIndexes('activity_log'))->pluck('columns')->all();

    expect($indexes)->toContain(['subject_type', 'subject_id', 'log_name']);
});
