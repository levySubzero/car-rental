<?php

use App\Enums\BookingStatus;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethodCode;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\GatewayTransaction;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Services\Payments\Gateways\DarajaClient;
use App\Services\Payments\Gateways\PaystackClient;
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
    $this->booking = Booking::factory()->awaitingPayment()->create(['reference' => 'BE-2026-000123']);
    BookingDriver::factory()->for($this->booking)->create();
});

function stkCallback(string $checkout, int $code = 0, ?string $receipt = 'TJK1AB2CD3'): array
{
    $callback = ['MerchantRequestID' => 'm-1', 'CheckoutRequestID' => $checkout, 'ResultCode' => $code, 'ResultDesc' => $code === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user'];

    if ($code === 0) {
        $callback['CallbackMetadata'] = ['Item' => [
            ['Name' => 'Amount', 'Value' => 16588.00],
            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ['Name' => 'TransactionDate', 'Value' => 20261025090102],
            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
        ]];
    }

    return ['Body' => ['stkCallback' => $callback]];
}

function fakeStkQuery(array $answer, int $status = 200): void
{
    Http::fake([
        DarajaClient::SANDBOX_URL.'/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        DarajaClient::SANDBOX_URL.'/mpesa/stkpushquery/v1/query' => Http::response($answer, $status),
    ]);
}

function c2bConfirmation(array $overrides = []): array
{
    return [
        'TransactionType' => 'Pay Bill', 'TransID' => 'TKQ1XYZ234', 'TransTime' => '20261025090500', 'TransAmount' => '16588.00',
        'BusinessShortCode' => '174379', 'BillRefNumber' => 'be2026 000123', 'InvoiceNumber' => '', 'OrgAccountBalance' => '',
        'ThirdPartyTransID' => '', 'MSISDN' => '2547 ***** 678', 'FirstName' => 'Amina', 'MiddleName' => '', 'LastName' => 'Otieno',
        ...$overrides,
    ];
}

function paystackPost(array $payload, ?string $secret = 'sk_test_1')
{
    $body = json_encode($payload);
    $headers = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $headers['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $body, $secret);
    }

    return test()->call('POST', route('hooks.paystack'), [], [], [], $headers, $body);
}

it('needs the secret in the Daraja callback URL, and an allowed IP when a list is set', function () {
    $this->postJson('/hooks/mobile/stk/wrong', stkCallback('ws_CO_1'))->assertNotFound();
    $this->postJson('/hooks/mobile/c2b/wrong/confirm', c2bConfirmation())->assertNotFound();

    config(['services.daraja.allowed_ips' => ['196.201.214.200']]);
    $this->postJson(route('hooks.mobile.stk', 'cb-secret'), stkCallback('ws_CO_1'))->assertNotFound();

    $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
        ->postJson(route('hooks.mobile.stk', 'cb-secret'), stkCallback('ws_CO_unknown'))
        ->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    expect(GatewayTransaction::count())->toBe(0);
});

it('refuses Daraja callbacks in production until the IP allowlist is set (M12.16)', function () {
    config(['services.daraja.env' => 'production']);

    $this->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation())->assertNotFound();
    expect(GatewayTransaction::count())->toBe(0)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);

    // Safaricom publishes ranges as well as single addresses.
    config(['services.daraja.allowed_ips' => ['196.201.214.0/24']]);

    $this->withServerVariables(['REMOTE_ADDR' => '196.201.215.1'])
        ->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation())->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.17'])
        ->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation())->assertOk();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('refuses Daraja callbacks entirely when no secret is configured', function () {
    config(['services.daraja.callback_secret' => null]);

    $this->postJson('/hooks/mobile/stk/', stkCallback('ws_CO_1'))->assertNotFound();
    $this->postJson('/hooks/mobile/stk/anything', stkCallback('ws_CO_1'))->assertNotFound();
});

it('reserves the booking from a paid STK callback once Daraja confirms it', function () {
    fakeStkQuery(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'Processed']);
    $transaction = GatewayTransaction::factory()->for($this->booking)->create(['external_ref' => 'ws_CO_1']);

    $this->postJson(route('hooks.mobile.stk', 'cb-secret'), stkCallback('ws_CO_1'))->assertOk()->assertJson(['ResultCode' => 0]);

    $transaction->refresh();
    expect($transaction->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($transaction->receipt)->toBe('TJK1AB2CD3')
        ->and($transaction->payload['callback'])->toMatchArray(['result_code' => 0, 'receipt' => 'TJK1AB2CD3'])
        ->and($transaction->payload['callback'])->not->toHaveKey('PhoneNumber')
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);

    // Safaricom retries: nothing more happens.
    $this->postJson(route('hooks.mobile.stk', 'cb-secret'), stkCallback('ws_CO_1'))->assertOk();
    expect(PaymentSubmission::count())->toBe(1);
});

it('trusts the query, not the callback', function () {
    fakeStkQuery(['ResponseCode' => '0', 'ResultCode' => '1032', 'ResultDesc' => 'Request cancelled by user']);
    $transaction = GatewayTransaction::factory()->for($this->booking)->create(['external_ref' => 'ws_CO_1']);

    // A forged "paid" callback.
    $this->postJson(route('hooks.mobile.stk', 'cb-secret'), stkCallback('ws_CO_1'))->assertOk();

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Failed)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

it('leaves the push for reconcile when Daraja cannot be asked', function () {
    fakeStkQuery(['errorMessage' => 'System busy'], 503);
    $transaction = GatewayTransaction::factory()->for($this->booking)->create(['external_ref' => 'ws_CO_1']);

    $this->postJson(route('hooks.mobile.stk', 'cb-secret'), stkCallback('ws_CO_1'))->assertOk();

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Initiated);
});

it('matches a paybill payment to the booking named in the account number', function () {
    $this->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation())
        ->assertOk()->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    $transaction = GatewayTransaction::query()->sole();
    expect($transaction->gateway)->toBe(PaymentGateway::MpesaC2b)
        ->and($transaction->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($transaction->booking_id)->toBe($this->booking->id)
        ->and($transaction->amount_minor)->toBe(1_658_800)
        ->and($transaction->payer_name)->toBe('Amina Otieno')
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved)
        ->and($this->booking->payments()->sole()->reference)->toBe('TKQ1XYZ234');

    $this->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation())->assertOk();
    expect(GatewayTransaction::count())->toBe(1)->and(PaymentSubmission::count())->toBe(1);
});

it('keeps a paybill payment with an unknown account number for staff', function () {
    $staff = staffWith([Permission::PaymentsVerify]);

    $this->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation(['BillRefNumber' => '0712345678']))->assertOk();

    expect(GatewayTransaction::query()->sole()->status)->toBe(GatewayTransactionStatus::Unmatched)
        ->and(GatewayTransaction::query()->sole()->payload['bill_ref'])->toBe('0712345678')
        ->and($staff->notifications()->count())->toBe(1)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

it('sends a short paybill payment to review', function () {
    staffWith([Permission::PaymentsVerify]);

    $this->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation(['TransAmount' => '5000']))->assertOk();

    expect(GatewayTransaction::query()->sole()->status)->toBe(GatewayTransactionStatus::NeedsReview)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

it('ignores confirmations for another shortcode or without a TransID', function (array $overrides) {
    $this->postJson(route('hooks.mobile.c2b.confirm', 'cb-secret'), c2bConfirmation($overrides))->assertOk();

    expect(GatewayTransaction::count())->toBe(0);
})->with([
    'other shortcode' => [['BusinessShortCode' => '600000']],
    'no TransID' => [['TransID' => '']],
    'no amount' => [['TransAmount' => 'abc']],
]);

it('accepts every payment at the validation URL', function () {
    $this->postJson(route('hooks.mobile.c2b.validate', 'cb-secret'), c2bConfirmation())->assertOk()->assertJson(['ResultCode' => 0]);

    expect(GatewayTransaction::count())->toBe(0);
});

it('refuses an unsigned or mis-signed Paystack event and reads nothing from it', function (?string $secret) {
    Http::fake();
    $transaction = GatewayTransaction::factory()->paystack()->for($this->booking)->create();

    paystackPost(['event' => 'charge.success', 'data' => ['reference' => $transaction->external_ref]], $secret)->assertUnauthorized();

    Http::assertNothingSent();
    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Initiated);
})->with(['unsigned' => [null], 'wrong key' => ['sk_someone_else']]);

it('confirms a signed charge.success with Paystack before applying it', function () {
    $transaction = GatewayTransaction::factory()->paystack()->for($this->booking)->create();
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(['status' => true, 'data' => [
        'id' => 7, 'status' => 'success', 'reference' => $transaction->external_ref, 'amount' => 1_658_800, 'currency' => 'KES',
    ]])]);

    paystackPost(['event' => 'charge.success', 'data' => ['reference' => $transaction->external_ref]])->assertOk();

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('applies a replayed Paystack event only once', function () {
    $transaction = GatewayTransaction::factory()->paystack()->for($this->booking)->create();
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(['status' => true, 'data' => [
        'id' => 7, 'status' => 'success', 'reference' => $transaction->external_ref, 'amount' => 1_658_800, 'currency' => 'KES',
    ]])]);
    $event = ['event' => 'charge.success', 'data' => ['reference' => $transaction->external_ref]];

    paystackPost($event)->assertOk();
    paystackPost($event)->assertOk();

    expect(PaymentSubmission::count())->toBe(1)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('ignores other Paystack events and unknown references', function (array $payload) {
    Http::fake();

    paystackPost($payload)->assertOk();

    Http::assertNothingSent();
})->with([
    'other event' => [['event' => 'transfer.success', 'data' => ['reference' => 'x']]],
    'unknown reference' => [['event' => 'charge.success', 'data' => ['reference' => 'NOT-OURS']]],
]);

it('needs no CSRF token or session for callbacks', function () {
    $response = $this->postJson(route('hooks.mobile.c2b.validate', 'cb-secret'), []);

    $response->assertOk();
    expect($response->headers->getCookies())->toBe([]);
});
