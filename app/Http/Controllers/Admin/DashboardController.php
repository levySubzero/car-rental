<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingDeskService;
use App\Services\Cms\SetupChecklistService;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Open to every staff member; each part is filtered by the viewer's permissions. The desk's
 * day (M11.10) for staff who see bookings, the payments waiting for those who check them,
 * and the setup checklist (stale rates, unfilled settings, payment gateways) in a popup.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SetupChecklistService $checklist, BookingDeskService $desk, PaymentVerificationService $payments): Response
    {
        $user = $request->user();
        $row = fn (Booking $booking) => [
            'reference' => $booking->reference,
            'car' => $booking->car->name,
            'renter' => $booking->driver?->fullName(),
            'phone' => $booking->driver?->phone,
            'pickup_at' => $booking->pickup_at->toIso8601String(),
            'return_at' => $booking->return_at->toIso8601String(),
            'pickup_mode' => $booking->pickup_mode->value,
            'return_mode' => $booking->return_mode->value,
        ];

        return Inertia::render('admin/dashboard', [
            'setupChecklist' => $checklist->outstanding($user),
            // The popup opens by itself once per session (M11B.10); the button reopens it.
            'setupReminderDismissed' => (bool) $request->session()->get(SetupReminderController::SESSION_KEY, false),
            'today' => $user->can(Permission::BookingsView)
                ? array_map(fn ($bookings) => $bookings->map($row)->all(), $desk->today())
                : null,
            'paymentsWaiting' => $user->can(Permission::PaymentsView) ? ($payments->counts()['pending'] ?? 0) : null,
        ]);
    }
}
