<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Notifications\Booking\BookingConfirmed;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    Notification::fake();
    $this->booking = Booking::factory()->awaitingPayment()->create();
    BookingDriver::factory()->for($this->booking)->create(['email' => 'renter@example.test']);
    $this->staff = staffWith([Permission::BookingsView, Permission::PaymentsView, Permission::PaymentsVerify]);
});

function markPaid(array $data = []): TestResponse
{
    return test()->actingAs(test()->staff)
        ->post(route('admin.bookings.mark-paid', test()->booking->reference), [
            'amount' => '16588',
            'method' => 'mpesa',
            'reference' => 'QWE123',
            'send_voucher' => true,
            ...$data,
        ]);
}

it('needs the payments verify permission', function () {
    $this->actingAs(staffWith([Permission::BookingsView]))
        ->post(route('admin.bookings.mark-paid', $this->booking->reference), ['amount' => '1', 'method' => 'mpesa', 'send_voucher' => true])
        ->assertForbidden();
});

it('reserves the booking, records the payment and emails the voucher', function () {
    markPaid()->assertSessionHasNoErrors()->assertRedirect();

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::Reserved)
        ->and($booking->reservation_paid_minor)->toBe(1_658_800)
        ->and($booking->balance_minor)->toBe(6_635_000 - 1_658_800)
        ->and($booking->voucher_sent_at)->not->toBeNull()
        ->and($booking->payments()->sole()->only(['status', 'reference', 'amount_minor']))
        ->toEqual(['status' => PaymentStatus::Verified, 'reference' => 'QWE123', 'amount_minor' => 1_658_800]);
    Notification::assertSentOnDemand(BookingConfirmed::class);
});

it('can leave the voucher for staff to send later', function () {
    markPaid(['send_voucher' => false])->assertSessionHasNoErrors();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Reserved)
        ->and($this->booking->fresh()->voucher_sent_at)->toBeNull();
    Notification::assertNotSentTo(new AnonymousNotifiable, BookingConfirmed::class);
});

it('verifies the cash submission already waiting instead of adding another', function () {
    $this->booking->forceFill(['status' => BookingStatus::AwaitingCash])->save();
    $this->booking->payments()->create(['payment_method_code' => 'cash', 'transaction_message' => 'Cash at the office']);

    markPaid(['method' => 'cash'])->assertSessionHasNoErrors();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Reserved)
        ->and($this->booking->payments()->sole()->status)->toBe(PaymentStatus::Verified);
});

it('refuses a part payment without the permission, and a booking already paid', function () {
    markPaid(['amount' => '100'])->assertRedirect();
    expect($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($this->booking->payments()->count())->toBe(0);

    $this->booking->forceFill(['status' => BookingStatus::Reserved])->save();
    markPaid()->assertRedirect();
    expect($this->booking->payments()->count())->toBe(0);
});
