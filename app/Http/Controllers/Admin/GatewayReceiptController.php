<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GatewayTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Receipt\AttachReceiptRequest;
use App\Http\Requests\Admin\Receipt\IndexReceiptRequest;
use App\Http\Requests\Admin\Receipt\ResolveReceiptRequest;
use App\Http\Resources\Admin\GatewayReceiptResource;
use App\Models\GatewayTransaction;
use App\Services\Payments\GatewayReceiptService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Online receipts staff resolve by hand (M11B.9): paybill payments naming no booking, and
 * online payments that could not be applied safely.
 */
class GatewayReceiptController extends Controller
{
    public function __construct(private readonly GatewayReceiptService $receipts) {}

    public function index(IndexReceiptRequest $request): Response
    {
        $filters = $request->validated();
        $filters['scope'] ??= 'open';

        return Inertia::render('admin/receipts/index', [
            'receipts' => GatewayReceiptResource::collection($this->receipts->paginate($filters)),
            'filters' => $filters,
            'openCount' => $this->receipts->openCount(),
        ]);
    }

    public function attach(AttachReceiptRequest $request, GatewayTransaction $receipt): RedirectResponse
    {
        $booking = $request->booking();
        $receipt = $this->receipts->attach($receipt, $booking, $request->user());

        Inertia::flash('toast', $receipt->status === GatewayTransactionStatus::Matched
            ? ['type' => 'success', 'message' => "Payment applied. {$booking->reference} is reserved."]
            : ['type' => 'error', 'message' => "Not applied to {$booking->reference}: {$receipt->failure_reason}"]);

        return back();
    }

    public function resolve(ResolveReceiptRequest $request, GatewayTransaction $receipt): RedirectResponse
    {
        $this->receipts->resolve($receipt, $request->outcome(), (string) $request->validated('note'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Receipt closed.']);

        return back();
    }
}
