<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\ResendVoucherRequest;
use App\Models\Booking;
use App\Services\Booking\BookingDeskService;
use App\Services\Notifications\BookingNotifier;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Resending the confirmation and replacing the renter's link (M11.5).
 */
class BookingVoucherController extends Controller
{
    public function resend(ResendVoucherRequest $request, Booking $booking, BookingNotifier $notifier): RedirectResponse
    {
        if (! in_array($booking->status, [BookingStatus::Reserved, BookingStatus::Active], true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only a confirmed booking has a voucher to send.']);

            return back();
        }

        $sent = $notifier->resendConfirmation($booking);
        $whatsapp = $notifier->confirmationWhatsAppLink($booking);

        Inertia::flash('toast', array_filter([
            'type' => $sent || $whatsapp !== null ? 'success' : 'error',
            'message' => $sent ? 'Voucher emailed with a new booking link.' : 'The confirmation email is turned off in Notification settings.',
            'action' => $whatsapp === null ? null : ['label' => 'Send on WhatsApp', 'url' => $whatsapp],
        ]));

        return back();
    }

    public function rotate(ResendVoucherRequest $request, Booking $booking, BookingDeskService $desk): RedirectResponse
    {
        $link = route('bookings.show', $desk->rotateLink($booking, $request->user()));
        $whatsapp = WhatsAppLinkBuilder::linkTo($booking->driver?->phone, "Here is the new link to your Baines Executives booking {$booking->reference}: {$link}");

        // The plain link exists only now, so it is shown once.
        Inertia::flash('toast', array_filter([
            'type' => 'success',
            'message' => "New renter link (earlier links stop working): {$link}",
            'action' => $whatsapp === null ? null : ['label' => 'Send on WhatsApp', 'url' => $whatsapp],
        ]));

        return back();
    }
}
