<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\EditBookingRequest;
use App\Http\Requests\Admin\Booking\ExtendBookingRequest;
use App\Models\Booking;
use App\Services\Booking\BookingChangeService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Extend a hire or edit a booking's trip, with a live preview of the new days and money.
 */
class BookingChangeController extends Controller
{
    public function __construct(private readonly BookingChangeService $changes) {}

    public function previewExtend(ExtendBookingRequest $request, Booking $booking): JsonResponse
    {
        return response()->json($this->changes->preview($booking, $request->tripData(), extend: true));
    }

    public function extend(ExtendBookingRequest $request, Booking $booking): RedirectResponse
    {
        $booking = $this->changes->apply($booking, $request->tripData(), $request->total(), $request->user(), extend: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} now runs to the new return. The balance due is {$this->balance($booking)}; send the voucher again if the renter needs it."]);

        return back();
    }

    public function previewEdit(EditBookingRequest $request, Booking $booking): JsonResponse
    {
        return response()->json($this->changes->preview($booking, $request->changes(), extend: false));
    }

    public function edit(EditBookingRequest $request, Booking $booking): RedirectResponse
    {
        $booking = $this->changes->apply($booking, $request->changes(), $request->total(), $request->user(), extend: false);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} updated. The balance due is {$this->balance($booking)}."]);

        return back();
    }

    private function balance(Booking $booking): string
    {
        return (new Money((int) $booking->balance_minor))->format();
    }
}
