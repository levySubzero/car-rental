<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FuelLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\RecordHandoverRequest;
use App\Http\Requests\Admin\Booking\RecordReturnRequest;
use App\Models\Booking;
use App\Services\Booking\HandoverService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Handing the car over and taking it back (M11.6, M11.7).
 */
class BookingHandoverController extends Controller
{
    public function __construct(private readonly HandoverService $handovers) {}

    public function handover(RecordHandoverRequest $request, Booking $booking): RedirectResponse
    {
        $this->handovers->recordHandover(
            $booking,
            (int) $request->validated('odometer_km'),
            FuelLevel::from((string) $request->validated('fuel_level')),
            $request->money('balance_received'),
            $request->money('deposit_received'),
            $request->text('notes'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} is on hire."]);

        return back();
    }

    public function return(RecordReturnRequest $request, Booking $booking): RedirectResponse
    {
        $this->handovers->recordReturn(
            $booking,
            (int) $request->validated('odometer_km'),
            FuelLevel::from((string) $request->validated('fuel_level')),
            $request->money('deposit_refunded'),
            $request->money('extra_charges'),
            $request->text('notes'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} is completed."]);

        return back();
    }
}
