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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
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
    PaymentMethod::factory()->create(['code' => PaymentMethodCode::Card, 'name' => 'Card', 'online_enabled' => true]);
    PaymentMethod::factory()->create(['code' => PaymentMethodCode::Bank, 'name' => 'Bank transfer']);

    $this->token = str_repeat('o', 64);
    $this->booking = Booking::factory()->awaitingPayment()->withToken($this->token)->create(['reference' => 'BE-2026-000123']);
    BookingDriver::factory()->for($this->booking)->create(['email' => 'amina@example.test', 'phone' => '0712 345 678']);

    Http::fake([
        DarajaClient::SANDBOX_URL.'/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        DarajaClient::SANDBOX_URL.'/mpesa/stkpush/v1/processrequest' => Http::response(['MerchantRequestID' => 'm', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0']),
        PaystackClient::URL.'/transaction/initialize' => Http::response(['status' => true, 'data' => ['access_code' => 'ac_1', 'authorization_url' => 'https://x', 'reference' => 'r']]),
        PaystackClient::URL.'/transaction/verify/*' => fn (Request $request) => Http::response(['status' => true, 'data' => [
            'id' => 5, 'status' => 'success', 'reference' => basename($request->url()), 'amount' => 1_658_800, 'currency' => 'KES',
        ]]),
    ]);
});

function toast(): ?array
{
    return session('inertia.flash_data')['toast'] ?? null;
}

it('tells the page which methods are online and where to pay by hand', function () {
    $this->get(route('bookings.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/booking/status')
            ->where('payment_methods.0.code', 'mpesa')
            ->where('payment_methods.0.online', true)
            ->where('payment_methods.1.online', true)
            ->where('payment_methods.2.online', false)
            ->where('mpesa_pay_to', ['number' => '174379', 'account' => 'BE2026000123', 'till' => false])
            ->where('online_payment', null));
});

it('shows no paybill or online methods without keys', function () {
    config(['services.daraja.passkey' => null, 'services.paystack.secret_key' => null]);

    $this->get(route('bookings.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment_methods.0.online', false)
            ->where('payment_methods.1.online', false)
            ->where('mpesa_pay_to', null));
});

it('sends the M-Pesa prompt and then shows the page waiting', function () {
    $this->post(route('bookings.payment.mpesa', $this->token), ['phone' => '0712 345 678'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(toast())->toMatchArray(['type' => 'success'])
        ->and(GatewayTransaction::query()->sole()->status)->toBe(GatewayTransactionStatus::Initiated);

    $this->get(route('bookings.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('online_payment.gateway', 'mpesa_stk')
            ->where('online_payment.status', 'initiated')
            ->where('online_payment.failure_reason', null));
});

it('explains a failed prompt so the renter can try again', function () {
    GatewayTransaction::factory()->for($this->booking)->create([
        'status' => GatewayTransactionStatus::Failed, 'failure_reason' => 'Request cancelled by user',
    ]);

    $this->get(route('bookings.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('online_payment.status', 'failed')
            ->where('online_payment.failure_reason', 'Request cancelled by user'));
});

it('forgets attempts older than half an hour', function () {
    GatewayTransaction::factory()->for($this->booking)->create(['created_at' => now()->subMinutes(31)]);

    $this->get(route('bookings.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('online_payment', null));
});

it('needs a phone, and refuses one M-Pesa cannot prompt', function () {
    $this->post(route('bookings.payment.mpesa', $this->token), [])->assertSessionHasErrors('phone');

    $this->post(route('bookings.payment.mpesa', $this->token), ['phone' => '020 123 4567']);
    expect(toast())->toMatchArray(['type' => 'error'])
        ->and(GatewayTransaction::count())->toBe(0);
});

it('opens a card payment for the popup and confirms it with Paystack', function () {
    $start = $this->postJson(route('bookings.payment.card', $this->token))
        ->assertOk()
        ->assertJsonStructure(['access_code', 'reference', 'public_key'])
        ->assertJson(['access_code' => 'ac_1', 'public_key' => 'pk_test_1'])
        ->json();

    $this->post(route('bookings.payment.card.confirm', $this->token), ['reference' => $start['reference']])->assertRedirect();

    expect(toast())->toMatchArray(['type' => 'success', 'message' => 'Payment received. Your booking is confirmed.'])
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('answers a card start the gateway refused with a JSON message', function () {
    PaymentMethod::query()->where('code', 'card')->update(['online_enabled' => false]);

    $this->postJson(route('bookings.payment.card', $this->token))
        ->assertUnprocessable()
        ->assertJsonPath('errors.method.0', 'This payment method is not available at the moment. Please choose another.');
});

it('needs the booking token', function () {
    $this->post(route('bookings.payment.mpesa', str_repeat('x', 64)), ['phone' => '0712345678'])->assertNotFound();
    $this->postJson(route('bookings.payment.card', str_repeat('x', 64)))->assertNotFound();
});

it('limits online payment tries per booking', function () {
    foreach (range(1, 10) as $try) {
        $this->post(route('bookings.payment.card.confirm', $this->token), ['reference' => "nope-{$try}"]);
    }

    $this->post(route('bookings.payment.card.confirm', $this->token), ['reference' => 'nope-11'])->assertTooManyRequests();
});

it('lets the Paystack popup run under the CSP', function () {
    $csp = $this->get(route('bookings.show', $this->token))->headers->get('Content-Security-Policy');

    expect($csp)->toContain('https://js.paystack.co')
        ->and($csp)->toMatch('/frame-src[^;]*https:\/\/checkout\.paystack\.com/')
        ->and($csp)->toMatch('/connect-src[^;]*https:\/\/api\.paystack\.co/');
});
