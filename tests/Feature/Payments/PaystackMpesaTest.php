<?php

use App\Enums\FinanceChannel;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethodCode;
use App\Enums\Permission;
use App\Exceptions\Domain\PaymentException;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\GatewayTransaction;
use App\Models\PaymentMethod;
use App\Services\Payments\Gateways\PaystackClient;
use App\Services\Payments\OnlinePaymentService;
use App\Settings\GatewaySettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));

    config([
        'services.mpesa.provider' => 'paystack',
        'services.daraja' => ['env' => 'sandbox', 'consumer_key' => null, 'consumer_secret' => null, 'shortcode' => '174379', 'passkey' => null, 'type' => 'paybill', 'till_number' => null, 'callback_secret' => 'cb', 'allowed_ips' => []],
        'services.paystack' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1'],
    ]);

    $this->mpesa = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Mpesa, 'name' => 'M-Pesa', 'online_enabled' => true]);
    $this->card = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Card, 'name' => 'Card', 'online_enabled' => true]);
    $this->booking = Booking::factory()->awaitingPayment()->create(['reference' => 'BE-2026-000123']);
    BookingDriver::factory()->for($this->booking)->create(['email' => 'amina@example.test', 'phone' => '0712 345 678']);
    $this->service = app(OnlinePaymentService::class);
});

function fakePaystackInitialize(string $code = 'mpesa-code'): void
{
    Http::fake([PaystackClient::URL.'/transaction/initialize' => Http::response(['status' => true, 'data' => ['access_code' => $code]])]);
}

function paystackMpesaVerified(string $reference, array $overrides = []): array
{
    return ['status' => true, 'data' => [
        'id' => 77, 'status' => 'success', 'reference' => $reference, 'currency' => 'KES',
        'channel' => 'mobile_money', 'gateway_response' => 'Approved', ...$overrides,
    ]];
}

it('makes M-Pesa online from the Paystack keys, without Daraja', function () {
    expect($this->service->isOnline($this->mpesa))->toBeTrue()
        ->and($this->service->mpesaPayTo($this->booking))->toBeNull();

    config(['services.paystack' => ['public_key' => null, 'secret_key' => null]]);

    expect(app(OnlinePaymentService::class)->isOnline($this->mpesa))->toBeFalse();
});

it('starts M-Pesa in a mobile-money-only Paystack transaction', function () {
    fakePaystackInitialize();

    $start = $this->service->startPaystackMpesa($this->booking);

    expect($start)->toMatchArray(['access_code' => 'mpesa-code', 'public_key' => 'pk_test_1']);
    Http::assertSent(fn (Request $request) => $request['channels'] === ['mobile_money'] && $request['currency'] === 'KES');

    expect(GatewayTransaction::query()->sole())
        ->gateway->toBe(PaymentGateway::PaystackMpesa)
        ->status->toBe(GatewayTransactionStatus::Initiated);
});

it('refuses the gateway that is not chosen', function () {
    expect(fn () => $this->service->startMpesa($this->booking, '0712345678'))->toThrow(PaymentException::class);

    config(['services.mpesa.provider' => 'daraja']);

    expect(fn () => $this->service->startPaystackMpesa($this->booking))->toThrow(PaymentException::class);
});

it('keeps card and M-Pesa attempts apart', function () {
    Http::fakeSequence(PaystackClient::URL.'/transaction/initialize')
        ->push(['status' => true, 'data' => ['access_code' => 'card-code']])
        ->push(['status' => true, 'data' => ['access_code' => 'mpesa-code']]);

    expect($this->service->startCard($this->booking)['access_code'])->toBe('card-code')
        ->and($this->service->startPaystackMpesa($this->booking)['access_code'])->toBe('mpesa-code')
        // Each reopens its own, not the other's.
        ->and($this->service->startCard($this->booking)['access_code'])->toBe('card-code')
        ->and($this->service->startPaystackMpesa($this->booking)['access_code'])->toBe('mpesa-code');

    Http::assertSentCount(2);
});

it('settles once as an M-Pesa payment through the popup, webhook and reconcile', function () {
    fakePaystackInitialize();
    $start = $this->service->startPaystackMpesa($this->booking);
    $reference = $start['reference'];
    $transaction = GatewayTransaction::query()->sole();

    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(paystackMpesaVerified($reference, ['amount' => $transaction->amount_minor]))]);

    $this->service->confirmCard($this->booking, $reference);

    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);
    $this->call('POST', route('hooks.paystack'), [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_1'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

    $this->travel(5)->minutes();
    $this->service->reconcile();

    $payments = $this->booking->payments()->get();
    expect($payments)->toHaveCount(1)
        ->and($payments->first()->payment_method_code)->toBe(PaymentMethodCode::Mpesa)
        ->and($transaction->refresh()->status)->toBe(GatewayTransactionStatus::Matched)
        ->and(FinanceChannel::forSubmission($payments->first(), $transaction->gateway))->toBe(FinanceChannel::Paystack);
});

it('lets the webhook settle an attempt the popup never reported', function () {
    fakePaystackInitialize();
    $reference = $this->service->startPaystackMpesa($this->booking)['reference'];
    $transaction = GatewayTransaction::query()->sole();
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(paystackMpesaVerified($reference, ['amount' => $transaction->amount_minor]))]);

    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);
    $this->call('POST', route('hooks.paystack'), [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_1'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

    expect($transaction->refresh()->status)->toBe(GatewayTransactionStatus::Matched);
});

it('still settles a Daraja push in flight after the switch', function () {
    config(['services.daraja.consumer_key' => 'ck', 'services.daraja.consumer_secret' => 'cs', 'services.daraja.passkey' => 'pk']);
    $transaction = GatewayTransaction::factory()->for($this->booking)->create([
        'gateway' => PaymentGateway::MpesaStk,
        'amount_minor' => (int) $this->booking->reservation_minor,
        'status' => GatewayTransactionStatus::Initiated,
        'created_at' => now()->subMinutes(5),
    ]);

    Http::fake([
        '*/oauth/*' => Http::response(['access_token' => 't', 'expires_in' => 3599]),
        '*/stkpushquery/*' => Http::response(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'ok']),
    ]);

    app(OnlinePaymentService::class)->reconcile();

    expect($transaction->refresh()->status)->toBe(GatewayTransactionStatus::Matched);
});

it('tells the booking page which gateway takes M-Pesa, and starts it from the page', function () {
    $token = str_repeat('o', 64);
    Booking::factory()->awaitingPayment()->withToken($token)->create();
    fakePaystackInitialize();

    $this->get(route('bookings.show', $token))->assertInertia(fn (Assert $page) => $page
        ->where('payment_methods.0.mpesa_provider', 'paystack')
        ->where('mpesa_pay_to', null));

    BookingDriver::factory()->for(Booking::query()->latest('id')->first())->create(['email' => 'amina@example.test']);

    $this->postJson(route('bookings.payment.mpesa.paystack', $token))
        ->assertOk()
        ->assertJsonPath('access_code', 'mpesa-code');

    // Daraja is not the chosen gateway, so no prompt goes out.
    $this->post(route('bookings.payment.mpesa', $token), ['phone' => '0712 345 678']);

    expect(GatewayTransaction::query()->where('gateway', PaymentGateway::MpesaStk)->exists())->toBeFalse();
});

it('asks the checklist for Paystack keys, not Daraja or C2B', function () {
    config(['services.paystack' => ['public_key' => null, 'secret_key' => null]]);
    $this->mpesa->update(['online_enabled' => false]);
    $staff = staffWith([Permission::PaymentMethodsView]);

    $items = collect($this->actingAs($staff)->get(route('admin.dashboard'))->viewData('page')['props']['setupChecklist'])->keyBy('key');

    expect($items['payments.mpesa']['detail'])->toContain('Paystack keys')
        ->not->toContain('Daraja')
        ->not->toContain('mpesa:register-c2b');

    expect(app(GatewaySettings::class)->c2b_registered_at)->toBeNull();
});
