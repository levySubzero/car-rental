<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingRequestStatus;
use App\Enums\ContactMessageStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingDeskService;
use App\Services\Booking\BookingRequestService;
use App\Services\Cms\ContactMessageService;
use App\Services\Cms\SetupChecklistService;
use App\Services\Finance\DashboardStatsService;
use App\Services\Payments\GatewayReceiptService;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Open to every staff member; each part is filtered by the viewer's permissions (M11C.6).
 * Figures and charts for those who see the money or the bookings, the queues waiting for
 * staff, the desk's day (M11.10), and the setup checklist in a popup (M11B.10). A widget the
 * viewer may not see is sent as null.
 */
class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        SetupChecklistService $checklist,
        BookingDeskService $desk,
        DashboardStatsService $stats,
        PaymentVerificationService $payments,
        GatewayReceiptService $receipts,
        BookingRequestService $requests,
        ContactMessageService $messages,
    ): Response {
        $user = $request->user();
        $canFinance = $user->can(Permission::FinanceView);
        $canBookings = $user->can(Permission::BookingsView);
        $canPayments = $user->can(Permission::PaymentsView);
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
            'today' => $canBookings
                ? array_map(fn ($bookings) => $bookings->map($row)->all(), $desk->today())
                : null,
            'widgets' => [
                'revenue' => $canFinance ? $stats->revenue() : null,
                'collected' => $canFinance ? $stats->collectedSeries() : null,
                'bookings' => $canBookings ? $stats->bookings() : null,
                'bookingsSeries' => $canBookings ? $stats->bookingsSeries() : null,
                'utilisation' => $canBookings ? $stats->utilisation() : null,
                'topCars' => $canBookings ? $stats->topCars() : null,
                // Queues are counted live: staff act on them.
                'paymentsWaiting' => $canPayments ? ($payments->counts()['pending'] ?? 0) : null,
                'receiptsOpen' => $canPayments ? $receipts->openCount() : null,
                'openRequests' => $user->can(Permission::BookingRequestsView)
                    ? ($requests->counts()[BookingRequestStatus::Open->value] ?? 0)
                    : null,
                'newMessages' => $user->can(Permission::ContactMessagesView)
                    ? ($messages->counts()[ContactMessageStatus::New->value] ?? 0)
                    : null,
            ],
        ]);
    }
}
