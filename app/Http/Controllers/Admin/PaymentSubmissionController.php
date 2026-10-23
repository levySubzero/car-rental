<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payment\IndexPaymentRequest;
use App\Http\Requests\Admin\Payment\RejectPaymentRequest;
use App\Http\Requests\Admin\Payment\VerifyPaymentRequest;
use App\Http\Resources\Admin\PaymentSubmissionResource;
use App\Models\PaymentSubmission;
use App\Services\Notifications\BookingNotifier;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The payments queue (M9): renter payments waiting to be checked, verified or rejected.
 * The bookings desk (M11.4) reuses the same actions.
 */
class PaymentSubmissionController extends Controller
{
    public function __construct(private readonly PaymentVerificationService $payments) {}

    public function index(IndexPaymentRequest $request): Response
    {
        $filters = $request->validated();
        $filters['status'] ??= PaymentStatus::Pending->value;

        return Inertia::render('admin/payments/index', [
            'payments' => PaymentSubmissionResource::collection($this->payments->paginate($filters)),
            'filters' => $filters,
            'counts' => $this->payments->counts(),
            'statusOptions' => array_map(fn (PaymentStatus $status) => ['value' => $status->value, 'label' => $status->label()], PaymentStatus::cases()),
        ]);
    }

    public function verify(VerifyPaymentRequest $request, PaymentSubmission $payment, BookingNotifier $notifier): RedirectResponse
    {
        $this->payments->verify($payment, (string) $request->validated('reference'), $request->amount(), $request->user(), $request->note());

        // Click-to-chat sends nothing itself: staff send the confirmation from the toast.
        $whatsapp = $notifier->confirmationWhatsAppLink($payment->booking);

        Inertia::flash('toast', array_filter([
            'type' => 'success',
            'message' => "Payment verified. {$payment->booking->reference} is reserved.",
            'action' => $whatsapp === null ? null : ['label' => 'Send on WhatsApp', 'url' => $whatsapp],
        ]));

        return back();
    }

    public function reject(RejectPaymentRequest $request, PaymentSubmission $payment): RedirectResponse
    {
        $this->payments->reject($payment, (string) $request->validated('reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment rejected. The renter can pay again.']);

        return back();
    }
}
