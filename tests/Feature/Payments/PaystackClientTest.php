<?php

use App\Services\Payments\Gateways\GatewayException;
use App\Services\Payments\Gateways\PaystackClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.paystack' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1']]);
    $this->client = fn () => app(PaystackClient::class);
});

function verifyAnswer(array $overrides = []): array
{
    return ['status' => true, 'message' => 'Verification successful', 'data' => [
        'id' => 4099260516,
        'status' => 'success',
        'reference' => 'BE2026000123-P1',
        'amount' => 1_658_800,
        'currency' => 'KES',
        'channel' => 'card',
        'gateway_response' => 'Approved',
        ...$overrides,
    ]];
}

it('initializes a card-only KES transaction under our reference', function () {
    Http::fake([PaystackClient::URL.'/transaction/initialize' => Http::response(['status' => true, 'message' => 'Authorization URL created', 'data' => [
        'authorization_url' => 'https://checkout.paystack.com/0peioxfhpn',
        'access_code' => '0peioxfhpn',
        'reference' => 'BE2026000123-P1',
    ]])]);

    $code = ($this->client)()->initialize('renter@example.com', 1_658_800, 'BE2026000123-P1', ['booking' => 'BE-2026-000123']);

    expect($code)->toBe('0peioxfhpn');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer sk_test_1')
        && $request['email'] === 'renter@example.com'
        && $request['amount'] === 1_658_800
        && $request['currency'] === 'KES'
        && $request['reference'] === 'BE2026000123-P1'
        && $request['channels'] === ['card']
        && $request['metadata'] === ['booking' => 'BE-2026-000123']);
});

it('reads a verified transaction', function () {
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(verifyAnswer())]);

    $transaction = ($this->client)()->verify('BE2026000123-P1');

    expect($transaction->isSuccessful())->toBeTrue()
        ->and($transaction->amountMinor)->toBe(1_658_800)
        ->and($transaction->currency)->toBe('KES')
        ->and($transaction->id)->toBe('4099260516');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === PaystackClient::URL.'/transaction/verify/BE2026000123-P1');
});

it('tells pending from finished-without-money', function (string $status, bool $successful, bool $pending) {
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(verifyAnswer(['status' => $status]))]);

    $transaction = ($this->client)()->verify('BE2026000123-P1');

    expect($transaction->isSuccessful())->toBe($successful)->and($transaction->isPending())->toBe($pending);
})->with([
    ['success', true, false],
    ['ongoing', false, true],
    ['pending', false, true],
    ['abandoned', false, false],
    ['failed', false, false],
    ['reversed', false, false],
]);

it('refuses an answer about another reference', function () {
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(verifyAnswer(['reference' => 'SOMEONE-ELSE']))]);

    ($this->client)()->verify('BE2026000123-P1');
})->throws(GatewayException::class, 'missing its status, amount or reference');

it('reports Paystack refusals with its message', function () {
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(['status' => false, 'message' => 'Transaction reference not found'], 400)]);

    ($this->client)()->verify('nope');
})->throws(GatewayException::class, 'Transaction reference not found');

it('checks the webhook signature against the raw body', function () {
    $body = '{"event":"charge.success","data":{"reference":"BE2026000123-P1"}}';
    $signature = hash_hmac('sha512', $body, 'sk_test_1');

    expect(($this->client)()->validSignature($body, $signature))->toBeTrue()
        ->and(($this->client)()->validSignature($body.' ', $signature))->toBeFalse()
        ->and(($this->client)()->validSignature($body, hash_hmac('sha512', $body, 'sk_other')))->toBeFalse()
        ->and(($this->client)()->validSignature($body, null))->toBeFalse();
});

it('does nothing without keys, and refuses every signature', function () {
    config(['services.paystack.secret_key' => null]);
    Http::fake();
    $body = '{}';

    expect(($this->client)()->configured())->toBeFalse()
        ->and(($this->client)()->validSignature($body, hash_hmac('sha512', $body, '')))->toBeFalse()
        ->and(fn () => ($this->client)()->initialize('renter@example.com', 100, 'R1'))->toThrow(GatewayException::class, 'not configured');

    Http::assertNothingSent();
});

it('refuses bad input before calling Paystack', function (string $email, int $amount, string $message) {
    Http::fake();

    expect(fn () => ($this->client)()->initialize($email, $amount, 'R1'))->toThrow(GatewayException::class, $message);

    Http::assertNothingSent();
})->with([
    ['not-an-email', 100, 'email address'],
    ['renter@example.com', 0, 'positive'],
]);
