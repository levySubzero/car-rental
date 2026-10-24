<?php

namespace App\Http\Controllers\Public;

use App\Enums\GatewayTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveBookingToken;
use App\Http\Requests\Public\Booking\ConfirmCardPaymentRequest;
use App\Http\Requests\Public\Booking\StartMpesaPaymentRequest;
use App\Services\Payments\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The renter pays the reservation online from their booking link (M11B.7, D42): an M-Pesa
 * prompt to their phone, or a Paystack card popup on the page. The status page polls while
 * a payment is in flight.
 */
class OnlinePaymentController extends Controller
{
    public function __construct(private readonly OnlinePaymentService $payments) {}

    public function mpesa(StartMpesaPaymentRequest $request): RedirectResponse
    {
        $this->payments->startMpesa($request->booking(), $request->phone());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Check your phone and enter your M-Pesa PIN to pay.']);

        return back();
    }

    /**
     * The access code for the popup, as JSON: the page opens Paystack itself.
     */
    public function card(Request $request): JsonResponse
    {
        return response()->json($this->payments->startCard(ResolveBookingToken::booking($request)));
    }

    public function confirmCard(ConfirmCardPaymentRequest $request): RedirectResponse
    {
        $transaction = $this->payments->confirmCard($request->booking(), $request->reference());

        Inertia::flash('toast', match ($transaction->status) {
            GatewayTransactionStatus::Matched => ['type' => 'success', 'message' => 'Payment received. Your booking is confirmed.'],
            GatewayTransactionStatus::Initiated => ['type' => 'success', 'message' => 'Payment is being processed. This page updates once it is confirmed.'],
            GatewayTransactionStatus::Failed => ['type' => 'error', 'message' => 'The card payment did not go through. Please try again.'],
            default => ['type' => 'success', 'message' => 'Payment received. Our team will confirm your booking shortly.'],
        });

        return back();
    }
}
