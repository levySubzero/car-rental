<?php

use App\Services\Payments\Gateways\DarajaClient;
use App\Services\Payments\Gateways\GatewayException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const SANDBOX = DarajaClient::SANDBOX_URL;

beforeEach(function () {
    config(['services.daraja' => [
        'env' => 'sandbox',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_test',
        'shortcode' => '174379',
        'passkey' => 'passkey',
        'type' => 'paybill',
        'till_number' => null,
        'callback_secret' => 'secret',
        'allowed_ips' => [],
    ]]);

    // 14:05:09 in Nairobi is 11:05:09 UTC.
    $this->travelTo(now('UTC')->setDate(2026, 10, 25)->setTime(11, 5, 9));
    $this->client = fn () => app(DarajaClient::class);
});

function fakeDaraja(array $routes = []): void
{
    Http::fake([
        SANDBOX.'/oauth/*' => Http::response(['access_token' => 'tok_1', 'expires_in' => '3599']),
        SANDBOX.'/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]),
        ...$routes,
    ]);
}

it('pushes the reservation to the phone with the paybill password and EAT timestamp', function () {
    fakeDaraja();

    $result = ($this->client)()->stkPush('0712 345 678', 1_658_800, 'BE2026000123', 'Booking BE2026000123', 'https://example.test/hooks/mobile/stk/secret');

    expect($result->checkoutRequestId)->toBe('ws_CO_191220191020363925');

    Http::assertSent(fn (Request $request) => $request->url() === SANDBOX.'/mpesa/stkpush/v1/processrequest'
        && $request->hasHeader('Authorization', 'Bearer tok_1')
        && $request['BusinessShortCode'] === '174379'
        && $request['Timestamp'] === '20261025140509'
        && $request['Password'] === base64_encode('174379passkey20261025140509')
        && $request['TransactionType'] === 'CustomerPayBillOnline'
        && $request['Amount'] === 16588
        && $request['PartyA'] === '254712345678'
        && $request['PartyB'] === '174379'
        && $request['PhoneNumber'] === '254712345678'
        && $request['AccountReference'] === 'BE2026000123'
        && strlen($request['TransactionDesc']) <= 13
        && $request['CallBackURL'] === 'https://example.test/hooks/mobile/stk/secret');
});

it('pays a till with buy goods, the store number as shortcode', function () {
    config(['services.daraja.type' => 'till', 'services.daraja.till_number' => '600100']);
    fakeDaraja();

    ($this->client)()->stkPush('0712345678', 100_000, 'BE2026000123', 'Booking', 'https://example.test/cb');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/processrequest')
        && $request['TransactionType'] === 'CustomerBuyGoodsOnline'
        && $request['BusinessShortCode'] === '174379'
        && $request['PartyB'] === '600100');
});

it('needs the till number when paying a till', function () {
    config(['services.daraja.type' => 'till']);

    expect(($this->client)()->configured())->toBeFalse()
        ->and(($this->client)()->payTo())->toBeNull();

    config(['services.daraja.till_number' => '600100']);

    expect(($this->client)()->configured())->toBeTrue()
        ->and(($this->client)()->payTo())->toBe('600100');
});

it('caches the OAuth token across calls and fetches a new one after a 401', function () {
    $accepted = ['MerchantRequestID' => '1', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0', 'CustomerMessage' => 'Success'];
    Http::fake([
        SANDBOX.'/oauth/*' => Http::response(['access_token' => 'tok_1', 'expires_in' => '3599']),
        SANDBOX.'/mpesa/stkpush/v1/processrequest' => Http::sequence()
            ->push($accepted)
            ->push($accepted)
            ->push(['errorCode' => '404.001.03', 'errorMessage' => 'Invalid Access Token'], 401),
    ]);

    ($this->client)()->stkPush('0712345678', 100_000, 'BE1', 'Booking', 'https://example.test/cb');
    ($this->client)()->stkPush('0712345678', 100_000, 'BE2', 'Booking', 'https://example.test/cb');

    expect(collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/oauth/'))->count())->toBe(1)
        ->and(Cache::has('daraja.token.'.sha1(SANDBOX.'|ck_test')))->toBeTrue();

    expect(fn () => ($this->client)()->stkPush('0712345678', 100_000, 'BE3', 'Booking', 'https://example.test/cb'))
        ->toThrow(GatewayException::class, 'Invalid Access Token');

    expect(Cache::has('daraja.token.'.sha1(SANDBOX.'|ck_test')))->toBeFalse();
});

it('refuses bad input before calling Safaricom', function (string $phone, int $amount, string $reference, string $message) {
    fakeDaraja();

    expect(fn () => ($this->client)()->stkPush($phone, $amount, $reference, 'Booking', 'https://example.test/cb'))
        ->toThrow(GatewayException::class, $message);

    Http::assertNothingSent();
})->with([
    'landline' => ['0201234567', 100_000, 'BE1', 'not a Kenyan mobile'],
    'cents' => ['0712345678', 100_050, 'BE1', 'whole shillings'],
    'zero' => ['0712345678', 0, 'BE1', 'whole shillings'],
    'long reference' => ['0712345678', 100_000, 'BE-2026-000123', '1 to 12 characters'],
]);

it('says so when the keys are missing', function () {
    config(['services.daraja.passkey' => null]);
    Http::fake();

    expect(($this->client)()->configured())->toBeFalse()
        ->and(fn () => ($this->client)()->stkPush('0712345678', 100_000, 'BE1', 'Booking', 'https://example.test/cb'))
        ->toThrow(GatewayException::class, 'not configured');

    Http::assertNothingSent();
});

it('reports an STK push Safaricom did not accept', function () {
    fakeDaraja([
        SANDBOX.'/mpesa/stkpush/v1/processrequest' => Http::response(['requestId' => '1', 'errorCode' => '400.002.02', 'errorMessage' => 'Bad Request - Invalid PhoneNumber'], 400),
    ]);

    expect(fn () => ($this->client)()->stkPush('0712345678', 100_000, 'BE1', 'Booking', 'https://example.test/cb'))
        ->toThrow(GatewayException::class, 'Invalid PhoneNumber');
});

it('reads the STK query as pending, paid or failed', function (int $status, array $body, string $state, ?string $code) {
    fakeDaraja([SANDBOX.'/mpesa/stkpushquery/v1/query' => Http::response($body, $status)]);

    $result = ($this->client)()->stkQuery('ws_CO_1');

    expect($result->state)->toBe($state)->and($result->resultCode)->toBe($code);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/query')
        && $request['CheckoutRequestID'] === 'ws_CO_1'
        && $request['Password'] === base64_encode('174379passkey20261025140509'));
})->with([
    'processing' => [500, ['requestId' => '1', 'errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], 'pending', null],
    'paid' => [200, ['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.'], 'paid', '0'],
    'cancelled' => [200, ['ResponseCode' => '0', 'ResultCode' => '1032', 'ResultDesc' => 'Request cancelled by user'], 'failed', '1032'],
    'no answer' => [200, ['ResponseCode' => '0', 'ResultCode' => '1037', 'ResultDesc' => 'DS timeout user cannot be reached'], 'failed', '1037'],
]);

it('treats other query errors as a failed request, not an outcome', function () {
    fakeDaraja([SANDBOX.'/mpesa/stkpushquery/v1/query' => Http::response(['errorCode' => '500.001.1001x', 'errorMessage' => 'Spike arrest'], 500)]);

    expect(fn () => ($this->client)()->stkQuery('ws_CO_1'))->toThrow(GatewayException::class, 'Spike arrest');
});

it('registers the C2B confirmation and validation URLs', function () {
    fakeDaraja([SANDBOX.'/mpesa/c2b/v2/registerurl' => Http::response(['OriginatorCoversationID' => '1', 'ResponseCode' => '0', 'ResponseDescription' => 'Success'])]);

    ($this->client)()->registerC2bUrls('https://example.test/hooks/mobile/c2b/secret/confirm', 'https://example.test/hooks/mobile/c2b/secret/validate');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/registerurl')
        && $request['ShortCode'] === '174379'
        && $request['ResponseType'] === 'Completed'
        && $request['ConfirmationURL'] === 'https://example.test/hooks/mobile/c2b/secret/confirm');
});

it('uses the production host and refuses to simulate there', function () {
    config(['services.daraja.env' => 'production']);
    Http::fake();

    expect(($this->client)()->baseUrl())->toBe(DarajaClient::PRODUCTION_URL)
        ->and(fn () => ($this->client)()->simulateC2b(100_000, 'BE2026000123'))->toThrow(GatewayException::class, 'sandbox');

    Http::assertNothingSent();
});
