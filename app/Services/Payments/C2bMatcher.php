<?php

namespace App\Services\Payments;

use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use App\Services\Payments\Gateways\DarajaClient;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Paybill payments a renter made by hand, as Safaricom's C2B confirmation reports them
 * (M11B.5, D42). The account number they typed is matched against the compact booking
 * reference (BE2026000123; spaces, dashes and case ignored) and the payment is settled like
 * any online one. A reference that names no booking is kept as unmatched for staff. Each
 * TransID is recorded once, so a repeated confirmation changes nothing.
 */
class C2bMatcher
{
    public function __construct(
        private readonly OnlinePaymentService $payments,
        private readonly DarajaClient $daraja,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): ?GatewayTransaction
    {
        $transId = is_string($payload['TransID'] ?? null) ? trim($payload['TransID']) : '';
        $amount = $payload['TransAmount'] ?? null;
        $shortcode = is_scalar($payload['BusinessShortCode'] ?? null) ? (string) $payload['BusinessShortCode'] : '';

        if ($transId === '' || ! is_numeric($amount)) {
            Log::warning('C2B confirmation without a TransID or amount ignored');

            return null;
        }

        if (! $this->daraja->ownsShortcode($shortcode)) {
            Log::warning('C2B confirmation for another shortcode ignored', ['shortcode' => $shortcode, 'trans_id' => $transId]);

            return null;
        }

        $existing = GatewayTransaction::query()->where('external_ref', $transId)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $minor = Money::fromMajor((string) $amount)->minor;
        } catch (InvalidArgumentException) {
            Log::warning('C2B confirmation with an unreadable amount ignored', ['trans_id' => $transId]);

            return null;
        }

        $billRef = is_scalar($payload['BillRefNumber'] ?? null) ? (string) $payload['BillRefNumber'] : '';

        try {
            $transaction = GatewayTransaction::query()->create([
                'gateway' => PaymentGateway::MpesaC2b,
                'external_ref' => $transId,
                'receipt' => $transId,
                'booking_id' => $this->bookingFor($billRef)?->id,
                'amount_minor' => $minor,
                'payer_phone' => is_scalar($payload['MSISDN'] ?? null) ? (string) $payload['MSISDN'] : null,
                'payer_name' => $this->payerName($payload),
                'status' => GatewayTransactionStatus::Paid,
                'payload' => [
                    'bill_ref' => $billRef,
                    'trans_time' => $payload['TransTime'] ?? null,
                    'type' => $payload['TransactionType'] ?? null,
                ],
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same confirmation arrived twice at once; the other request records it.
            return GatewayTransaction::query()->where('external_ref', $transId)->first();
        }

        return $this->payments->settle($transaction);
    }

    /**
     * The booking an account number names: BE2026000123 however it was typed.
     */
    public function bookingFor(string $billRef): ?Booking
    {
        $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $billRef));

        if (preg_match('/^BE(\d{4})(\d{6})$/', $compact, $parts) !== 1) {
            return null;
        }

        return Booking::query()->where('reference', "BE-{$parts[1]}-{$parts[2]}")->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payerName(array $payload): ?string
    {
        $name = trim(implode(' ', array_filter(
            [$payload['FirstName'] ?? null, $payload['MiddleName'] ?? null, $payload['LastName'] ?? null],
            fn ($part) => is_string($part) && trim($part) !== '',
        )));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }
}
