<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\EditBookingRequest;
use App\Http\Requests\Admin\Booking\ExtendBookingRequest;
use App\Models\Booking;
use App\Services\Booking\BookingChangeService;
use App\Services\Booking\BookingExtensionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Extend a hire (a booking of its own, D50) or edit a booking's trip, each with a live preview
 * of the new days and money.
 */
class BookingChangeController extends Controller
{
    public function __construct(
        private readonly BookingChangeService $changes,
        private readonly BookingExtensionService $extensions,
    ) {}

    public function previewExtend(ExtendBookingRequest $request, Booking $booking): JsonResponse
    {
        return response()->json($this->extensions->preview($booking, $request->returnAt()));
    }

    public function extend(ExtendBookingRequest $request, Booking $booking): RedirectResponse
    {
        $extension = $this->extensions->create(
            $booking, $request->returnAt(), $request->total(), $request->payment(), $request->user(),
            $request->paid(), $request->paymentMethod(), $request->text('reference'),
        );

        $amount = (new Money((int) $extension->total_minor))->format();

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->payment() === BookingExtensionService::PAY_LINK
            ? "Extension {$extension->reference} added ({$amount}). The renter has been sent the link to pay; it is confirmed once paid."
            : "Extension {$extension->reference} confirmed ({$amount}). The renter has been sent the updated voucher."]);

        return back();
    }

    public function previewEdit(EditBookingRequest $request, Booking $booking): JsonResponse
    {
        return response()->json($this->changes->preview($booking, $request->changes()));
    }

    public function edit(EditBookingRequest $request, Booking $booking): RedirectResponse
    {
        $booking = $this->changes->apply($booking, $request->changes(), $request->total(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} updated. The balance due is {$this->balance($booking)}."]);

        return back();
    }

    private function balance(Booking $booking): string
    {
        return (new Money((int) $booking->balance_minor))->format();
    }
}
