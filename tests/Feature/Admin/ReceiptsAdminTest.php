<?php

use App\Enums\BookingStatus;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\GatewayTransaction;
use App\Models\PaymentSubmission;
use App\Services\Payments\OnlinePaymentService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));

    $this->booking = Booking::factory()->awaitingPayment()->create(['reference' => 'BE-2026-000123']);
    BookingDriver::factory()->for($this->booking)->create();
    $this->unmatched = GatewayTransaction::factory()->unmatched('0712345678')->create(['amount_minor' => 1_658_800, 'payer_name' => 'Amina Otieno']);
    $this->staff = staffWith([Permission::PaymentsView, Permission::PaymentsVerify]);
});

it('sends guests away and refuses staff without the payments permissions', function () {
    $this->get(route('admin.receipts.index'))->assertRedirect(route('login'));

    $this->actingAs(staffWith([Permission::BookingsView]))->get(route('admin.receipts.index'))->assertForbidden();

    $viewer = staffWith([Permission::PaymentsView]);
    $this->actingAs($viewer)->get(route('admin.receipts.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.receipts.attach', $this->unmatched), ['booking' => 'BE-2026-000123'])->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.receipts.resolve', $this->unmatched), ['outcome' => 'refunded', 'note' => 'x'])->assertForbidden();
});

it('lists receipts to resolve by default, and every online payment on request', function () {
    GatewayTransaction::factory()->paid()->create(['status' => GatewayTransactionStatus::Matched]);
    GatewayTransaction::factory()->create();

    $this->actingAs($this->staff)->get(route('admin.receipts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/receipts/index')
            ->where('openCount', 1)
            ->has('receipts.data', 1)
            ->where('receipts.data.0.account', '0712345678')
            ->where('receipts.data.0.payer_name', 'Amina Otieno')
            ->where('receipts.data.0.gateway_label', 'M-Pesa paybill')
            ->where('receipts.data.0.open', true));

    // Every online payment, but never an attempt still waiting on the renter.
    $this->actingAs($this->staff)->get(route('admin.receipts.index', ['scope' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('receipts.data', 2));
});

it('finds receipts by the account number typed', function () {
    GatewayTransaction::factory()->unmatched('SOMETHINGELSE')->create();

    $this->actingAs($this->staff)->get(route('admin.receipts.index', ['search' => '0712']))
        ->assertInertia(fn (Assert $page) => $page->has('receipts.data', 1)->where('receipts.data.0.id', $this->unmatched->id));
});

it('applies a receipt to the booking it was meant for', function () {
    $this->actingAs($this->staff)
        ->post(route('admin.receipts.attach', $this->unmatched), ['booking' => ' be-2026-000123 '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $receipt = $this->unmatched->fresh();
    expect($receipt->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($receipt->booking_id)->toBe($this->booking->id)
        ->and($receipt->resolved_by)->toBe($this->staff->id)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved)
        ->and($this->booking->payments()->sole()->verified_by)->toBe($this->staff->id)
        ->and(session('inertia.flash_data')['toast']['type'])->toBe('success')
        ->and(Activity::query()->where('event', 'receipt-attached')->exists())->toBeTrue();
});

it('keeps the receipt open with the reason when it still cannot be applied', function () {
    $this->booking->forceFill(['status' => BookingStatus::Expired])->save();

    $this->actingAs($this->staff)->post(route('admin.receipts.attach', $this->unmatched), ['booking' => 'BE-2026-000123']);

    expect($this->unmatched->fresh()->status)->toBe(GatewayTransactionStatus::NeedsReview)
        ->and($this->unmatched->fresh()->failure_reason)->toContain('no longer takes a payment')
        ->and(session('inertia.flash_data')['toast']['type'])->toBe('error')
        ->and(PaymentSubmission::count())->toBe(0)
        // No alert to the staff member who tried.
        ->and($this->staff->notifications()->count())->toBe(0);
});

it('needs a booking reference that exists', function () {
    $this->actingAs($this->staff)->post(route('admin.receipts.attach', $this->unmatched), ['booking' => 'BE-2026-999999'])
        ->assertSessionHasErrors('booking');
    $this->actingAs($this->staff)->post(route('admin.receipts.attach', $this->unmatched), [])
        ->assertSessionHasErrors('booking');
});

it('closes a receipt as refunded or with a note', function (string $outcome, GatewayTransactionStatus $status) {
    $this->actingAs($this->staff)
        ->post(route('admin.receipts.resolve', $this->unmatched), ['outcome' => $outcome, 'note' => ' Reversed: RKT1234 '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $receipt = $this->unmatched->fresh();
    expect($receipt->status)->toBe($status)
        ->and($receipt->resolution_note)->toBe('Reversed: RKT1234')
        ->and($receipt->resolved_by)->toBe($this->staff->id)
        ->and(Activity::query()->where('event', "receipt-{$outcome}")->exists())->toBeTrue();
})->with([
    ['refunded', GatewayTransactionStatus::Refunded],
    ['ignored', GatewayTransactionStatus::Ignored],
]);

it('needs a note and a known outcome to close', function () {
    $this->actingAs($this->staff)->post(route('admin.receipts.resolve', $this->unmatched), ['outcome' => 'matched', 'note' => ''])
        ->assertSessionHasErrors(['outcome', 'note']);
});

it('refuses to act twice on a receipt', function () {
    $matched = GatewayTransaction::factory()->paid()->create(['gateway' => PaymentGateway::MpesaC2b, 'status' => GatewayTransactionStatus::Matched]);

    $this->actingAs($this->staff)->post(route('admin.receipts.resolve', $matched), ['outcome' => 'refunded', 'note' => 'x']);
    expect(session('inertia.flash_data')['toast'])->toMatchArray(['type' => 'error', 'message' => 'This payment has already been dealt with.'])
        ->and($matched->fresh()->status)->toBe(GatewayTransactionStatus::Matched);

    $this->actingAs($this->staff)->post(route('admin.receipts.attach', $matched), ['booking' => 'BE-2026-000123']);
    expect(session('inertia.flash_data')['toast']['type'])->toBe('error');
});

it('points the review alert at the receipts page', function () {
    $staff = staffWith([Permission::PaymentsVerify]);

    app(OnlinePaymentService::class)->settle(GatewayTransaction::factory()->unmatched()->create(['status' => GatewayTransactionStatus::Paid]));

    expect($staff->notifications()->sole()->data['url'])->toBe(route('admin.receipts.index'));
});
