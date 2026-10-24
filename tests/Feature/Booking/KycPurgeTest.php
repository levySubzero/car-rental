<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\Media;
use App\Services\Booking\BookingStatusService;
use App\Services\Booking\KycPurgeService;
use App\Settings\RetentionSettings;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $settings = app(RetentionSettings::class);
    $settings->kyc_retention_months = 12;
    $settings->draft_retention_days = 7;
    $settings->save();
});

function closedBooking(int $monthsAgo, bool $legalHold = false): Booking
{
    $booking = Booking::factory()->reserved()->create([
        'status' => BookingStatus::Completed,
        'closed_at' => now()->subMonths($monthsAgo)->subDay(),
        'legal_hold' => $legalHold,
    ]);
    BookingDriver::factory()->for($booking)->create(['id_number' => '12345678', 'kra_pin' => 'A123456789Z', 'licence_number' => 'DL-1']);
    Media::factory()->private()->attachedTo($booking, 'booking.licence')->create();
    Media::factory()->private()->attachedTo($booking, 'booking.voucher')->create();

    return $booking;
}

it('purges KYC after the retention period and keeps the voucher and the contacts', function () {
    $old = closedBooking(13);
    $recent = closedBooking(11);

    $counts = app(KycPurgeService::class)->purge();

    expect($counts['bookings'])->toBe(1)
        ->and($old->fresh()->kyc_purged_at)->not->toBeNull()
        ->and($old->mediaIn('booking.licence')->count())->toBe(0)
        ->and($old->mediaIn('booking.voucher')->count())->toBe(1)
        ->and($old->driver->fresh()->id_number)->toBe('')
        ->and($old->driver->fresh()->kra_pin)->toBeNull()
        ->and($old->driver->fresh()->surname)->not->toBe('')
        ->and($recent->fresh()->kyc_purged_at)->toBeNull()
        ->and($recent->mediaIn('booking.licence')->count())->toBe(1);
});

it('keeps KYC under a legal hold', function () {
    $held = closedBooking(30, legalHold: true);

    app(KycPurgeService::class)->purge();

    expect($held->fresh()->kyc_purged_at)->toBeNull()
        ->and($held->driver->fresh()->id_number)->toBe('12345678');
});

it('purges each booking once', function () {
    closedBooking(13);

    expect(app(KycPurgeService::class)->purge()['bookings'])->toBe(1)
        ->and(app(KycPurgeService::class)->purge()['bookings'])->toBe(0);
});

it('deletes expired drafts that were never submitted after the draft retention', function () {
    $old = Booking::factory()->create(['status' => BookingStatus::Expired]);
    $old->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();
    $fresh = Booking::factory()->create(['status' => BookingStatus::Expired]);
    $submitted = Booking::factory()->awaitingPayment()->create(['status' => BookingStatus::Expired]);
    $submitted->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();

    expect(app(KycPurgeService::class)->purge()['drafts'])->toBe(1)
        ->and(Booking::find($old->id))->toBeNull()
        ->and(Booking::find($fresh->id))->not->toBeNull()
        ->and(Booking::find($submitted->id))->not->toBeNull();
});

it('stamps closed_at on the end states only', function () {
    $booking = Booking::factory()->reserved()->create();

    app(BookingStatusService::class)->transition($booking, BookingStatus::Active, 'system');
    expect($booking->fresh()->closed_at)->toBeNull();

    app(BookingStatusService::class)->transition($booking, BookingStatus::Completed, 'system');
    expect($booking->fresh()->closed_at)->not->toBeNull();
});

it('schedules the purge and the activity log pruning', function () {
    $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

    expect($commands)->toContain('kyc:purge')->toContain('activity:prune');
});
