<?php

use App\Enums\BookingStatus;
use App\Enums\GatewayTransactionStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use App\Models\Media;
use App\Models\PaymentSubmission;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->booking = Booking::factory()->awaitingPayment()->create(['status' => BookingStatus::PaymentSubmitted, 'expires_at' => null]);
    $this->submission = PaymentSubmission::factory()->for($this->booking)->create();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
});

it('refuses staff without the payments permissions', function () {
    $this->actingAs(staffWith([Permission::BookingsView]))->get(route('admin.payments.index'))->assertForbidden();

    $viewer = staffWith([Permission::PaymentsView]);
    $this->actingAs($viewer)
        ->post(route('admin.payments.verify', $this->submission), ['reference' => 'R', 'amount' => '16588'])
        ->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('admin.payments.reject', $this->submission), ['reason' => 'No'])
        ->assertForbidden();
});

it('lists pending payments first by default', function () {
    PaymentSubmission::factory()->verified()->create();

    $this->actingAs(staffWith([Permission::PaymentsView]))
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/index')
            ->where('filters.status', 'pending')
            ->has('payments.data', 1)
            ->where('payments.data.0.booking.reference', $this->booking->reference)
            ->where('payments.data.0.booking.reservation.major', '16588.00')
            ->where('counts.verified', 1));
});

it('searches by booking reference', function () {
    PaymentSubmission::factory()->create();

    $this->actingAs(staffWith([Permission::PaymentsView]))
        ->get(route('admin.payments.index', ['search' => $this->booking->reference]))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
});

it('validates the verify and reject forms', function () {
    $staff = staffWith([Permission::PaymentsView, Permission::PaymentsVerify, Permission::PaymentsReject]);

    $this->actingAs($staff)
        ->post(route('admin.payments.verify', $this->submission), ['reference' => '', 'amount' => '-5'])
        ->assertSessionHasErrors(['reference', 'amount']);
    $this->actingAs($staff)
        ->post(route('admin.payments.reject', $this->submission), ['reason' => ''])
        ->assertSessionHasErrors('reason');
});

it('verifies and rejects from the queue', function () {
    $staff = staffWith([Permission::PaymentsView, Permission::PaymentsVerify, Permission::PaymentsReject]);

    $this->actingAs($staff)
        ->post(route('admin.payments.verify', $this->submission), ['reference' => 'TJK1', 'amount' => '16588.00'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);

    $other = PaymentSubmission::factory()->create();
    $other->booking->forceFill(['status' => BookingStatus::PaymentSubmitted])->save();

    $this->actingAs($staff)
        ->post(route('admin.payments.reject', $other), ['reason' => 'Not received'])
        ->assertSessionHasNoErrors();
    expect($other->booking->fresh()->status)->toBe(BookingStatus::PaymentRejected);
});

it('shows a part payment error on the amount field', function () {
    $staff = staffWith([Permission::PaymentsView, Permission::PaymentsVerify]);

    $this->actingAs($staff)
        ->post(route('admin.payments.verify', $this->submission), ['reference' => 'TJK1', 'amount' => '100'])
        ->assertRedirect();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::PaymentSubmitted);
});

it('opens the payment proof for payment staff, not the KYC documents', function () {
    $proof = Media::factory()->private()->attachedTo($this->booking, 'booking.payment_proof')->create(['mime_type' => 'application/pdf']);
    $licence = Media::factory()->private()->attachedTo($this->booking, 'booking.licence')->create(['mime_type' => 'application/pdf']);

    $staff = staffWith([Permission::PaymentsView]);

    expect($staff->can('view', $proof))->toBeTrue()
        ->and($staff->can('view', $licence))->toBeFalse();
});

it('marks payments that arrived online as verified automatically', function () {
    $online = GatewayTransaction::factory()->paid()->create(['status' => GatewayTransactionStatus::Matched]);
    $submission = PaymentSubmission::factory()->for($online->booking)->verified()->create(['verified_by' => null]);
    $submission->forceFill(['gateway_transaction_id' => $online->id])->save();

    $this->actingAs(staffWith([Permission::PaymentsView]))
        ->get(route('admin.payments.index', ['status' => 'verified']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.online', ['gateway' => 'mpesa_stk', 'label' => 'M-Pesa STK'])
            ->where('payments.data.0.verified_by', 'Verified automatically'));

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.bookings.show', $online->booking->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.payments.0.online.label', 'M-Pesa STK')
            ->where('booking.payments.0.verified_by', 'Verified automatically'));
});
