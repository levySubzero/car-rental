<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\AcceptBookingRequest;
use App\Models\Booking;
use App\Services\Booking\BookingAcceptanceService;
use App\Settings\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Accepting a website request. Declining goes through BookingCancellationController.
 */
class BookingAcceptanceController extends Controller
{
    public function accept(AcceptBookingRequest $request, Booking $booking, BookingAcceptanceService $acceptance, NotificationSettings $settings): RedirectResponse
    {
        $acceptance->accept($booking, $request->optionalMoney('total'), $request->optionalMoney('deposit'), $request->user());

        // Without that mail the renter hears nothing and keeps their current link.
        Inertia::flash('toast', $settings->enabled('booking_accepted', 'mail')
            ? ['type' => 'success', 'message' => "{$booking->reference} is accepted. The renter has been emailed the link to pay."]
            : ['type' => 'error', 'message' => "{$booking->reference} is accepted, but the acceptance email is turned off: contact the renter yourself."]);

        return back();
    }
}
