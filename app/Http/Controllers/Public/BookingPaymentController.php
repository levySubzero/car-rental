<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Booking\SubmitPaymentRequest;
use App\Services\Payments\PaymentSubmissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The renter pays the reservation from their booking link (M9.2, M9.3).
 */
class BookingPaymentController extends Controller
{
    public function store(SubmitPaymentRequest $request, PaymentSubmissionService $payments): RedirectResponse
    {
        $submission = $payments->submit($request->booking(), $request->paymentMethod(), $request->transactionMessage(), $request->proof());

        Inertia::flash('toast', ['type' => 'success', 'message' => $submission->isCash()
            ? 'Noted: you will pay cash at our office.'
            : 'Payment received. We will check it and confirm your booking.']);

        return back();
    }
}
