<?php

namespace App\Http\Controllers\Hooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\Gateways\PaystackClient;
use App\Services\Payments\GatewayWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Paystack's events (M11B.5). The signature over the raw body is checked first; an unsigned or
 * mis-signed call is refused and nothing is read from it.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackClient $paystack, GatewayWebhookService $webhooks): Response
    {
        if (! $paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response('', 401);
        }

        $webhooks->paystackEvent($request->json()->all());

        return response('', 200);
    }
}
