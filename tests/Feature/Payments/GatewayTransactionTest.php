<?php

use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethodCode;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use App\Models\PaymentSubmission;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
});

it('keeps the payer phone encrypted at rest and out of serialised output', function () {
    $transaction = GatewayTransaction::factory()->create(['payer_phone' => '254712345678']);

    $raw = DB::table('gateway_transactions')->where('id', $transaction->id)->value('payer_phone');

    expect($raw)->not->toContain('712345678')
        ->and($transaction->fresh()->payer_phone)->toBe('254712345678')
        ->and($transaction->toArray())->not->toHaveKeys(['payer_phone', 'payload']);
});

it('refuses a second row for the same gateway reference', function () {
    GatewayTransaction::factory()->create(['external_ref' => 'ws_CO_1']);

    GatewayTransaction::factory()->create(['external_ref' => 'ws_CO_1']);
})->throws(UniqueConstraintViolationException::class);

it('links a booking, its online payments and the submission they created', function () {
    $transaction = GatewayTransaction::factory()->paid()->create();
    $submission = PaymentSubmission::factory()->for($transaction->booking)->create();
    $submission->forceFill(['gateway_transaction_id' => $transaction->id])->save();
    $transaction->forceFill(['payment_submission_id' => $submission->id])->save();

    expect($transaction->booking->gatewayTransactions->pluck('id')->all())->toBe([$transaction->id])
        ->and($submission->fresh()->isOnline())->toBeTrue()
        ->and($submission->fresh()->gatewayTransaction->is($transaction))->toBeTrue()
        ->and($transaction->fresh()->submission->is($submission))->toBeTrue()
        ->and(PaymentSubmission::factory()->create()->isOnline())->toBeFalse();
});

it('lists only receipts staff still have to deal with', function () {
    GatewayTransaction::factory()->create();
    GatewayTransaction::factory()->paid()->create(['status' => GatewayTransactionStatus::Matched]);
    $unmatched = GatewayTransaction::factory()->unmatched()->create();
    $review = GatewayTransaction::factory()->paid()->create(['status' => GatewayTransactionStatus::NeedsReview]);

    expect(GatewayTransaction::query()->awaitingStaff()->pluck('id')->sort()->values()->all())
        ->toBe([$unmatched->id, $review->id]);
});

it('gives the 12-character reference Daraja accepts', function () {
    $booking = Booking::factory()->awaitingPayment()->create(['reference' => 'BE-2026-000123']);

    expect($booking->compactReference())->toBe('BE2026000123')
        ->and(strlen($booking->compactReference()))->toBe(12)
        ->and(Booking::factory()->make(['reference' => null])->compactReference())->toBeNull();
});

it('maps each gateway to its payment method', function () {
    expect(PaymentGateway::MpesaStk->method())->toBe(PaymentMethodCode::Mpesa)
        ->and(PaymentGateway::MpesaC2b->method())->toBe(PaymentMethodCode::Mpesa)
        ->and(PaymentGateway::Paystack->method())->toBe(PaymentMethodCode::Card);
});
