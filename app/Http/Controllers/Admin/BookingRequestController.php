<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingRequest\HandleBookingRequestRequest;
use App\Http\Requests\Admin\BookingRequest\IndexBookingRequestRequest;
use App\Http\Resources\Admin\BookingRequestResource;
use App\Models\BookingRequest;
use App\Services\Booking\BookingRequestService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renter requests from the portal (M10B), under Operations. The booking desk (M11) will show
 * them on the booking as well.
 */
class BookingRequestController extends Controller
{
    public function __construct(private readonly BookingRequestService $requests) {}

    public function index(IndexBookingRequestRequest $request): Response
    {
        $filters = $request->validated();
        $filters['status'] ??= BookingRequestStatus::Open->value;

        return Inertia::render('admin/booking-requests/index', [
            'requests' => BookingRequestResource::collection($this->requests->paginate($filters)),
            'filters' => $filters,
            'counts' => $this->requests->counts(),
            'statusOptions' => array_map(fn (BookingRequestStatus $status) => ['value' => $status->value, 'label' => $status->label()], BookingRequestStatus::cases()),
        ]);
    }

    public function handle(HandleBookingRequestRequest $request, BookingRequest $bookingRequest): RedirectResponse
    {
        $this->requests->handle($bookingRequest, $request->validated('note'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request marked as handled.']);

        return back();
    }
}
