<?php

use App\Enums\BookingStatus;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentMethodCode;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\GatewayTransaction;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\DarajaClient;
use App\Services\Payments\Gateways\PaystackClient;
use App\Services\Payments\OnlinePaymentService;
use App\Settings\GatewaySettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));

    config([
        'services.daraja' => [
            'env' => 'sandbox', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'shortcode' => '174379',
            'passkey' => 'pk', 'type' => 'paybill', 'till_number' => null, 'callback_secret' => 'cb-secret', 'allowed_ips' => [],
        ],
        'services.paystack' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1'],
    ]);

    PaymentMethod::factory()->create(['code' => PaymentMethodCode::Mpesa, 'name' => 'M-Pesa', 'online_enabled' => true]);
    $this->booking = Booking::factory()->awaitingPayment()->create();
    BookingDriver::factory()->for($this->booking)->create();
});

function fakeQuery(array $answer, int $status = 200): void
{
    Http::fake([
        DarajaClient::SANDBOX_URL.'/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        DarajaClient::SANDBOX_URL.'/mpesa/stkpushquery/v1/query' => Http::response($answer, $status),
    ]);
}

it('settles pushes the callback never reported, leaving fresh ones alone', function () {
    fakeQuery(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'Processed']);
    $old = GatewayTransaction::factory()->for($this->booking)->create(['created_at' => now()->subMinutes(3)]);
    $fresh = GatewayTransaction::factory()->create(['created_at' => now()->subMinute()]);

    $this->artisan('payments:reconcile')->expectsOutputToContain('1 checked: 1 paid, 0 failed')->assertSuccessful();

    expect($old->fresh()->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($fresh->fresh()->status)->toBe(GatewayTransactionStatus::Initiated)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/query') && $request['CheckoutRequestID'] === $old->external_ref);
});

it('verifies card payments left open', function () {
    $transaction = GatewayTransaction::factory()->paystack()->for($this->booking)->create(['created_at' => now()->subMinutes(5)]);
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(['status' => true, 'data' => [
        'id' => 1, 'status' => 'abandoned', 'reference' => $transaction->external_ref, 'amount' => 1_658_800, 'currency' => 'KES',
    ]])]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Failed);
});

it('gives up on an attempt still unanswered after 30 minutes, and counts unreachable gateways', function () {
    fakeQuery(['errorMessage' => 'System busy'], 503);
    $stale = GatewayTransaction::factory()->for($this->booking)->create(['created_at' => now()->subMinutes(31)]);
    $recent = GatewayTransaction::factory()->create(['created_at' => now()->subMinutes(10)]);

    $this->artisan('payments:reconcile')->expectsOutputToContain('2 checked: 0 paid, 1 failed, 2 could not be asked')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(GatewayTransactionStatus::Failed)
        ->and($stale->fresh()->failure_reason)->toContain('30 minutes')
        ->and($recent->fresh()->status)->toBe(GatewayTransactionStatus::Initiated);
});

it('still applies a payment reported after it was given up on', function () {
    fakeQuery(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'Processed']);
    $transaction = GatewayTransaction::factory()->for($this->booking)->create(['status' => GatewayTransactionStatus::Failed]);

    app(OnlinePaymentService::class)->refreshStk($transaction, 'LATE123');

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('is scheduled every five minutes', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'payments:reconcile'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *');
});

it('registers the C2B URLs and remembers it for the configured paybill', function () {
    Http::fake([
        DarajaClient::SANDBOX_URL.'/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        DarajaClient::SANDBOX_URL.'/mpesa/c2b/v2/registerurl' => Http::response(['ResponseCode' => '0', 'ResponseDescription' => 'Success']),
    ]);
    $service = fn () => app(OnlinePaymentService::class);

    expect($service()->c2bRegistered())->toBeFalse();

    $this->artisan('mpesa:register-c2b')->expectsOutputToContain('registered')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/registerurl')
        && $request['ConfirmationURL'] === route('hooks.mobile.c2b.confirm', 'cb-secret')
        && $request['ValidationURL'] === route('hooks.mobile.c2b.validate', 'cb-secret'));
    expect(app(GatewaySettings::class)->c2b_registered_shortcode)->toBe('174379')
        ->and($service()->c2bRegistered())->toBeTrue();

    // A new paybill needs registering again.
    config(['services.daraja.shortcode' => '600100']);
    expect($service()->c2bRegistered())->toBeFalse();
});

it('reports a failed registration without recording it', function () {
    Http::fake([
        DarajaClient::SANDBOX_URL.'/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        DarajaClient::SANDBOX_URL.'/mpesa/c2b/v2/registerurl' => Http::response(['errorMessage' => 'Duplicate notification info'], 400),
    ]);

    $this->artisan('mpesa:register-c2b')->expectsOutputToContain('Duplicate notification info')->assertFailed();

    expect(app(GatewaySettings::class)->c2b_registered_at)->toBeNull();
});
