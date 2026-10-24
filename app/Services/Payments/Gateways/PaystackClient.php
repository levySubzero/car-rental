<?php

namespace App\Services\Payments\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Paystack (M11B.3, D42), called directly over HTTP. The server initializes a card-only KES
 * transaction under our own reference and hands the access code to Paystack's inline popup,
 * so the renter never leaves the booking page. Whatever the popup or a webhook claims, money
 * counts only once `verify()` says `success` for the amount we asked.
 */
class PaystackClient
{
    public const URL = 'https://api.paystack.co';

    public function __construct(
        private readonly ?string $publicKey,
        private readonly ?string $secretKey,
    ) {}

    public function name(): string
    {
        return 'Paystack';
    }

    public function configured(): bool
    {
        return filled($this->publicKey) && filled($this->secretKey);
    }

    public function publicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Start a card payment and return the access code for the popup.
     *
     * @param  array<string, scalar>  $metadata
     */
    public function initialize(string $email, int $amountMinor, string $reference, array $metadata = []): string
    {
        if ($amountMinor <= 0) {
            throw GatewayException::invalidInput($this->name(), 'the amount must be positive');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw GatewayException::invalidInput($this->name(), 'the email address is not valid');
        }

        $data = $this->data($this->send('post', '/transaction/initialize', [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => 'KES',
            'reference' => $reference,
            'channels' => ['card'],
            'metadata' => $metadata,
        ]));

        $accessCode = $data['access_code'] ?? null;

        if (! is_string($accessCode) || $accessCode === '') {
            throw GatewayException::invalid($this->name(), 'no access code in the answer');
        }

        return $accessCode;
    }

    public function verify(string $reference): PaystackTransaction
    {
        $data = $this->data($this->send('get', '/transaction/verify/'.rawurlencode($reference)));

        if (! is_string($data['status'] ?? null) || ! is_int($data['amount'] ?? null) || ($data['reference'] ?? null) !== $reference) {
            throw GatewayException::invalid($this->name(), 'the transaction is missing its status, amount or reference');
        }

        return new PaystackTransaction(
            status: $data['status'],
            reference: $reference,
            amountMinor: $data['amount'],
            currency: is_string($data['currency'] ?? null) ? $data['currency'] : '',
            id: isset($data['id']) && is_scalar($data['id']) ? (string) $data['id'] : null,
            channel: is_string($data['channel'] ?? null) ? $data['channel'] : null,
            gatewayResponse: is_string($data['gateway_response'] ?? null) ? $data['gateway_response'] : null,
        );
    }

    /**
     * Paystack signs each webhook with HMAC-SHA512 of the raw body under the secret key.
     */
    public function validSignature(string $rawBody, ?string $signature): bool
    {
        if (blank($this->secretKey) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $this->secretKey), $signature);
    }

    /**
     * @param  'get'|'post'  $method
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload = []): Response
    {
        if (! $this->configured()) {
            throw GatewayException::notConfigured($this->name());
        }

        $request = Http::acceptJson()->asJson()->timeout(15)->withToken((string) $this->secretKey);

        try {
            return $method === 'get'
                // Reads are safe to repeat; an initialize is not retried.
                ? $request->retry(2, 500, throw: false)->get(self::URL.$path)
                : $request->post(self::URL.$path, $payload);
        } catch (ConnectionException $exception) {
            throw GatewayException::request($this->name(), $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        // Paystack explains failures in "message"; the key never appears there.
        if ($response->failed() || $response->json('status') !== true) {
            throw GatewayException::request($this->name(), (string) ($response->json('message') ?? 'HTTP '.$response->status()));
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw GatewayException::invalid($this->name(), 'no data in the answer');
        }

        return $data;
    }
}
