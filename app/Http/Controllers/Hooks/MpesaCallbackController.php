<?php

namespace App\Http\Controllers\Hooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\C2bMatcher;
use App\Services\Payments\GatewayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Safaricom's calls (M11B.5): STK results and C2B paybill confirmations. Behind
 * VerifyDarajaCallback. Daraja is always told the call was accepted: a problem on our side
 * is for us to resolve, and a refusal would only make it retry.
 */
class MpesaCallbackController extends Controller
{
    public function stk(Request $request, GatewayWebhookService $webhooks): JsonResponse
    {
        $webhooks->stkResult($request->json()->all());

        return $this->accepted();
    }

    public function confirm(Request $request, C2bMatcher $matcher): JsonResponse
    {
        $matcher->record($request->json()->all());

        return $this->accepted();
    }

    /**
     * Only called if Safaricom enables external validation for the shortcode: every payment
     * is accepted, and matching happens on confirmation.
     */
    public function validatePayment(): JsonResponse
    {
        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
