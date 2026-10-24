<?php

namespace App\Services\Payments\Gateways;

use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Safaricom's Daraja API (M11B.2, D42), called directly over HTTP: an OAuth token, the STK
 * push (Lipa na M-Pesa Online) and its query, and C2B URL registration for paybill payments.
 *
 * The token lasts an hour and is cached until a minute before it expires, keyed by the
 * consumer key so a rotated key fetches a fresh one. A paybill pushes with
 * CustomerPayBillOnline to the shortcode; a till pushes with CustomerBuyGoodsOnline, where
 * the shortcode is the store number and the money goes to the till. Amounts are whole
 * shillings: the reservation is already rounded up (`ceil_major`).
 */
class DarajaClient
{
    public const SANDBOX_URL = 'https://sandbox.safaricom.co.ke';

    public const PRODUCTION_URL = 'https://api.safaricom.co.ke';

    // Daraja's answer to a query while the renter has not yet responded on their phone.
    private const STILL_PROCESSING = '500.001.1001';

    // AccountReference and TransactionDesc limits.
    private const MAX_REFERENCE = 12;

    private const MAX_DESCRIPTION = 13;

    /**
     * @param  array{env?: string|null, consumer_key?: string|null, consumer_secret?: string|null, shortcode?: string|null, passkey?: string|null, type?: string|null, till_number?: string|null, callback_secret?: string|null, allowed_ips?: list<string>}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'Daraja';
    }

    /**
     * Every key the STK push and the callbacks need is in the environment.
     */
    public function configured(): bool
    {
        $required = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey', 'callback_secret'];

        if ($this->isTill()) {
            $required[] = 'till_number';
        }

        return array_all($required, fn (string $key) => filled($this->config[$key] ?? null));
    }

    public function isSandbox(): bool
    {
        return ($this->config['env'] ?? 'sandbox') !== 'production';
    }

    public function isTill(): bool
    {
        return ($this->config['type'] ?? 'paybill') === 'till';
    }

    /**
     * Where the renter pays by hand: the paybill, or the till number.
     */
    public function payTo(): ?string
    {
        return $this->isTill() ? ($this->config['till_number'] ?? null) : ($this->config['shortcode'] ?? null);
    }

    /**
     * A C2B confirmation names the shortcode paid: ours is the paybill, or the till and its store.
     */
    public function ownsShortcode(string $shortcode): bool
    {
        $ours = array_filter([$this->config['shortcode'] ?? null, $this->config['till_number'] ?? null], fn ($code) => is_string($code) && $code !== '');

        return $shortcode !== '' && in_array($shortcode, $ours, true);
    }

    public function callbackSecret(): ?string
    {
        return $this->config['callback_secret'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function allowedIps(): array
    {
        return $this->config['allowed_ips'] ?? [];
    }

    public function baseUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Prompt the renter's phone to pay. Safaricom answers at once with the CheckoutRequestID;
     * the outcome comes on the callback.
     */
    public function stkPush(string $phone, int $amountMinor, string $accountReference, string $description, string $callbackUrl): StkPushResult
    {
        $msisdn = PhoneNumber::kenyanMobile($phone)
            ?? throw GatewayException::invalidInput($this->name(), 'the phone is not a Kenyan mobile number');

        if ($amountMinor <= 0 || $amountMinor % 100 !== 0) {
            throw GatewayException::invalidInput($this->name(), 'M-Pesa takes whole shillings only');
        }

        if ($accountReference === '' || strlen($accountReference) > self::MAX_REFERENCE) {
            throw GatewayException::invalidInput($this->name(), 'the account reference must be 1 to 12 characters');
        }

        $this->ensureConfigured();
        $timestamp = now('Africa/Nairobi')->format('YmdHis');
        $shortcode = $this->required('shortcode');

        $body = $this->post('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.$this->required('passkey').$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => $this->isTill() ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount' => intdiv($amountMinor, 100),
            'PartyA' => $msisdn,
            'PartyB' => $this->isTill() ? $this->required('till_number') : $shortcode,
            'PhoneNumber' => $msisdn,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => mb_substr($description, 0, self::MAX_DESCRIPTION),
        ])->json();

        if (! is_array($body) || ($body['ResponseCode'] ?? null) !== '0' || blank($body['CheckoutRequestID'] ?? null)) {
            throw GatewayException::invalid($this->name(), (string) ($body['ResponseDescription'] ?? 'the STK push was not accepted'));
        }

        return new StkPushResult(
            (string) ($body['MerchantRequestID'] ?? ''),
            (string) $body['CheckoutRequestID'],
            (string) ($body['CustomerMessage'] ?? ''),
        );
    }

    /**
     * Ask Daraja how an STK push ended. Used to confirm a callback before trusting it and to
     * settle pushes whose callback never arrived.
     */
    public function stkQuery(string $checkoutRequestId): StkQueryResult
    {
        $this->ensureConfigured();
        $timestamp = now('Africa/Nairobi')->format('YmdHis');
        $shortcode = $this->required('shortcode');

        $response = $this->send('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.$this->required('passkey').$timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);

        if ($response->json('errorCode') === self::STILL_PROCESSING) {
            return StkQueryResult::pending((string) $response->json('errorMessage'));
        }

        $this->throwIfFailed($response);
        $code = $response->json('ResultCode');

        if ($code === null || ! is_scalar($code)) {
            throw GatewayException::invalid($this->name(), 'the query answer has no ResultCode');
        }

        return StkQueryResult::fromResultCode((string) $code, (string) ($response->json('ResultDesc') ?? ''));
    }

    /**
     * Tell Safaricom where to report paybill payments. Production allows this once per
     * shortcode; changing the URLs later goes through Safaricom support.
     */
    public function registerC2bUrls(string $confirmationUrl, string $validationUrl): void
    {
        $this->ensureConfigured();

        $body = $this->post('/mpesa/c2b/v2/registerurl', [
            'ShortCode' => $this->required('shortcode'),
            // Without external validation enabled, Safaricom completes every payment.
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL' => $validationUrl,
        ])->json();

        if (! is_array($body) || ! in_array($body['ResponseCode'] ?? null, ['0', '00000000'], true)) {
            throw GatewayException::invalid($this->name(), (string) ($body['ResponseDescription'] ?? 'the URLs were not registered'));
        }
    }

    /**
     * Sandbox only: make Safaricom report a paybill payment, as a renter paying by hand would.
     */
    public function simulateC2b(int $amountMinor, string $billReference, string $phone = '254708374149'): void
    {
        if (! $this->isSandbox()) {
            throw GatewayException::invalidInput($this->name(), 'payments can only be simulated in the sandbox');
        }

        $this->ensureConfigured();

        $this->post('/mpesa/c2b/v1/simulate', [
            'ShortCode' => $this->required('shortcode'),
            'CommandID' => $this->isTill() ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
            'Amount' => intdiv($amountMinor, 100),
            'Msisdn' => $phone,
            'BillRefNumber' => $billReference,
        ]);
    }

    private function token(): string
    {
        $cached = Cache::get($this->tokenKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->retry(2, 500, throw: false)
                ->withBasicAuth($this->required('consumer_key'), $this->required('consumer_secret'))
                ->get($this->baseUrl().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $exception) {
            throw GatewayException::request($this->name(), $exception->getMessage());
        }

        $this->throwIfFailed($response);
        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw GatewayException::invalid($this->name(), 'no access token in the OAuth answer');
        }

        $ttl = max(60, (int) $response->json('expires_in', 3599) - 60);
        Cache::put($this->tokenKey(), $token, $ttl);

        return $token;
    }

    private function tokenKey(): string
    {
        return 'daraja.token.'.sha1($this->baseUrl().'|'.($this->config['consumer_key'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        $response = $this->send($path, $payload);
        $this->throwIfFailed($response);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload): Response
    {
        try {
            return $this->http()->post($this->baseUrl().$path, $payload);
        } catch (ConnectionException $exception) {
            throw GatewayException::request($this->name(), $exception->getMessage());
        }
    }

    private function http(): PendingRequest
    {
        // No automatic retry: a repeated STK push would prompt the renter twice.
        return Http::acceptJson()->asJson()->timeout(15)->withToken($this->token());
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 401) {
            Cache::forget($this->tokenKey());
        }

        // Daraja explains errors in errorMessage; neither keys nor tokens appear there.
        $reason = $response->json('errorMessage') ?? $response->json('ResponseDescription') ?? 'HTTP '.$response->status();

        throw GatewayException::request($this->name(), (string) $reason);
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw GatewayException::notConfigured($this->name());
        }
    }

    private function required(string $key): string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : throw GatewayException::notConfigured($this->name());
    }
}
