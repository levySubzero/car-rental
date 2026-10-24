<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\CancelBookingRequest;
use App\Http\Requests\Admin\Booking\MarkRefundedRequest;
use App\Models\Booking;
use App\Services\Booking\CancellationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Cancelling, no-shows and the refund paid back by hand (M11.8, M11.14).
 */
class BookingCancellationController extends Controller
{
    public function __construct(private readonly CancellationService $cancellations) {}

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->cancellations->cancel($booking, (string) $request->validated('reason'), $request->money('refund'), $request->text('refund_override_reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} is cancelled and the car is free again."]);

        return back();
    }

    public function noShow(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->cancellations->noShow($booking, (string) $request->validated('reason'), $request->money('refund'), $request->text('refund_override_reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} is marked as a no-show."]);

        return back();
    }

    public function refunded(MarkRefundedRequest $request, Booking $booking): RedirectResponse
    {
        $this->cancellations->markRefunded($booking, (string) $request->validated('reference'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund recorded.']);

        return back();
    }
}
