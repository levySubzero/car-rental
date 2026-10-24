<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Domain\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\MarkDeliveryCheckedRequest;
use App\Http\Requests\Admin\Booking\UpdateDeliveryRequest;
use App\Models\Booking;
use App\Services\Booking\DeliveryAdjustmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Checking a delivery against its zone (M11.13, D33).
 */
class BookingDeliveryController extends Controller
{
    public function __construct(private readonly DeliveryAdjustmentService $deliveries) {}

    public function update(UpdateDeliveryRequest $request, Booking $booking): RedirectResponse
    {
        $zone = $request->zone();
        $fee = $request->validated('fee') === null ? null : Money::fromMajor((string) $request->validated('fee'));

        $booking = $zone !== null && $zone->id !== $booking->delivery_zone_id
            ? $this->deliveries->changeZone($booking, $zone, $fee, $request->user())
            : $this->deliveries->setFee($booking, $fee ?? throw BookingException::zoneFeeNeeded(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery updated. The balance due is now '.(new Money((int) $booking->balance_minor))->format().'.']);

        return back();
    }

    public function checked(MarkDeliveryCheckedRequest $request, Booking $booking): RedirectResponse
    {
        $this->deliveries->markChecked($booking, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Address checked against the zone.']);

        return back();
    }
}
