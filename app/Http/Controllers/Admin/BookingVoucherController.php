<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\ResendVoucherRequest;
use App\Http\Requests\Admin\Booking\UpdateVoucherRequest;
use App\Http\Resources\Media\MediaResource;
use App\Models\Booking;
use App\Services\Booking\BookingDeskService;
use App\Services\Notifications\BookingNotifier;
use App\Services\Notifications\VoucherService;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reviewing, editing and sending the voucher, and replacing the renter's link (M11.5).
 */
class BookingVoucherController extends Controller
{
    public function show(Booking $booking, VoucherService $vouchers): Response
    {
        Gate::authorize('viewVoucher', $booking);
        abort_unless($vouchers->hasRecord($booking), 404);

        // Staff can get here before the queued job that makes it has run. A void booking gets
        // no new voucher, so it may have none.
        $voucher = $vouchers->current($booking) ?? ($vouchers->isAvailable($booking) ? $vouchers->generate($booking) : null);
        $booking->loadMissing(['car', 'driver']);

        return Inertia::render('admin/bookings/voucher', [
            'booking' => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'email' => $booking->driver?->email,
                'phone' => $booking->driver?->phone,
                'sent_at' => $booking->voucher_sent_at?->toIso8601String(),
            ],
            'manageable' => $vouchers->isManageable($booking),
            'void' => $vouchers->isVoid($booking),
            'voucher' => $voucher === null ? null : MediaResource::make($voucher),
            'fields' => $vouchers->editable($booking),
            'original' => $vouchers->editable($booking, withOverrides: false),
            'notes' => $booking->voucher_overrides['notes'] ?? '',
        ]);
    }

    public function update(UpdateVoucherRequest $request, Booking $booking, VoucherService $vouchers): RedirectResponse
    {
        if (! $vouchers->isManageable($booking)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This voucher can no longer be edited: the hire is over or the booking was cancelled.']);

            return back();
        }

        $vouchers->update($booking, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voucher saved. It has not been sent.']);

        return back();
    }

    public function resend(ResendVoucherRequest $request, Booking $booking, BookingNotifier $notifier, VoucherService $vouchers): RedirectResponse
    {
        if (! $vouchers->isManageable($booking)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This voucher can no longer be sent: the hire is over or the booking was cancelled.']);

            return back();
        }

        $sent = $notifier->resendConfirmation($booking);
        $whatsapp = $notifier->confirmationWhatsAppLink($booking);

        Inertia::flash('toast', array_filter([
            'type' => $sent || $whatsapp !== null ? 'success' : 'error',
            'message' => match (true) {
                $sent => 'Voucher emailed with a new booking link.',
                blank($booking->driver?->email) => 'This client has no email address. Download or print the voucher instead.',
                default => 'The confirmation email is turned off in Notification settings.',
            },
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
