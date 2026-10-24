<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Models\GatewayTransaction;
use App\Services\Payments\Gateways\GatewayException;
use Illuminate\Support\Facades\Log;

/**
 * What the gateways tell us after the fact (M11B.5, D42). A callback is only a nudge: an STK
 * result is confirmed with Daraja's query and a Paystack event with its verify endpoint
 * before any money is applied, so a forged or replayed call changes nothing. When the
 * gateway cannot be asked right now, the attempt stays initiated for `payments:reconcile`.
 */
class GatewayWebhookService
{
    public function __construct(private readonly OnlinePaymentService $payments) {}

    /**
     * Daraja's STK result: Body.stkCallback with the CheckoutRequestID, ResultCode and, when
     * paid, the receipt in CallbackMetadata.
     *
     * @param  array<string, mixed>  $payload
     */
    public function stkResult(array $payload): void
    {
        $callback = data_get($payload, 'Body.stkCallback');
        $checkout = is_array($callback) ? ($callback['CheckoutRequestID'] ?? null) : null;

        if (! is_string($checkout) || $checkout === '') {
            Log::warning('STK callback without a CheckoutRequestID ignored');

            return;
        }

        $transaction = $this->find(PaymentGateway::MpesaStk, $checkout);

        if ($transaction === null) {
            Log::warning('STK callback for an unknown push ignored', ['checkout' => $checkout]);

            return;
        }

        $items = [];
        $metadata = data_get($callback, 'CallbackMetadata.Item');

        foreach (is_array($metadata) ? $metadata : [] as $item) {
            if (is_array($item) && is_string($item['Name'] ?? null)) {
                $items[$item['Name']] = $item['Value'] ?? null;
            }
        }

        $receipt = is_scalar($items['MpesaReceiptNumber'] ?? null) ? (string) $items['MpesaReceiptNumber'] : null;

        // Kept for staff: the outcome as reported, never the phone number.
        $transaction->forceFill(['payload' => [...($transaction->payload ?? []), 'callback' => [
            'result_code' => $callback['ResultCode'] ?? null,
            'result_desc' => $callback['ResultDesc'] ?? null,
            'receipt' => $receipt,
            'amount' => $items['Amount'] ?? null,
        ]]])->save();

        try {
            $this->payments->refreshStk($transaction, $receipt);
        } catch (GatewayException $exception) {
            report($exception);
        }
    }

    /**
     * A Paystack event whose signature has been checked. Only charge.success matters.
     *
     * @param  array<string, mixed>  $payload
     */
    public function paystackEvent(array $payload): void
    {
        if (($payload['event'] ?? null) !== 'charge.success') {
            return;
        }

        $reference = data_get($payload, 'data.reference');
        $transaction = is_string($reference) ? $this->find(PaymentGateway::Paystack, $reference) : null;

        if ($transaction === null) {
            Log::warning('Paystack charge for an unknown reference ignored', ['reference' => $reference]);

            return;
        }

        try {
            $this->payments->refreshPaystack($transaction);
        } catch (GatewayException $exception) {
            report($exception);
        }
    }

    private function find(PaymentGateway $gateway, string $reference): ?GatewayTransaction
    {
        return GatewayTransaction::query()->where('gateway', $gateway)->where('external_ref', $reference)->first();
    }
}
