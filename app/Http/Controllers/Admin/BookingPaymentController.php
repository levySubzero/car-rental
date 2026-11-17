<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\MarkPaidRequest;
use App\Models\Booking;
use App\Services\Notifications\BookingNotifier;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * "Mark as paid" on the booking page: reserves the booking, and by default sends the voucher.
 */
class BookingPaymentController extends Controller
{
    public function markPaid(MarkPaidRequest $request, Booking $booking, PaymentVerificationService $payments, BookingNotifier $notifier): RedirectResponse
    {
        $payments->markPaidByStaff($booking, $request->money('amount'), $request->paymentMethod(), $request->text('reference'), $request->user(), $request->text('note'));

        $sent = $request->boolean('send_voucher') && $notifier->resendConfirmation($booking->fresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $sent => "{$booking->reference} is paid and reserved. The voucher was emailed to the renter.",
            $request->boolean('send_voucher') => "{$booking->reference} is paid and reserved. The voucher could not be emailed (no address, or the email is off): send it from the voucher page.",
            default => "{$booking->reference} is paid and reserved. Review the voucher, then send it.",
        }]);

        return back();
    }
}
