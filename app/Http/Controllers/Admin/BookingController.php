<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingBucket;
use App\Enums\FuelLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\IndexBookingRequest;
use App\Http\Requests\Admin\Booking\LegalHoldRequest;
use App\Http\Resources\Admin\BookingListResource;
use App\Http\Resources\Admin\BookingResource;
use App\Models\Booking;
use App\Models\Car;
use App\Models\DeliveryZone;
use App\Models\PaymentMethod;
use App\Services\Booking\BookingDeskService;
use App\Services\Booking\BookingStatusService;
use App\Services\Booking\CancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bookings desk (M11.1, M11.2): the list staff work from and one booking with
 * everything needed to act on it. The actions have their own controllers.
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingDeskService $desk) {}

    public function index(IndexBookingRequest $request): Response
    {
        $filters = $request->validated();
        $filters['bucket'] ??= BookingBucket::Upcoming->value;

        return Inertia::render('admin/bookings/index', [
            'bookings' => BookingListResource::collection($this->desk->paginate($filters)),
            'filters' => $filters,
            'counts' => $this->desk->counts(),
            'buckets' => array_map(fn (BookingBucket $bucket) => ['value' => $bucket->value, 'label' => $bucket->label()], BookingBucket::cases()),
            'cars' => Car::query()->orderBy('name')->get(['id', 'name'])->map(fn (Car $car) => ['value' => (string) $car->id, 'label' => $car->name]),
            'methods' => PaymentMethod::query()->ordered()->get()->map(fn (PaymentMethod $method) => ['value' => $method->code->value, 'label' => $method->name]),
        ]);
    }

    public function show(Booking $booking, BookingStatusService $statuses, CancellationService $cancellations): Response
    {
        Gate::authorize('view', $booking);

        $booking->load(['car', 'driver', 'deliveryZone', 'payments.method', 'payments.proof', 'payments.verifier:id,name', 'payments.rejecter:id,name', 'handover.recorder:id,name', 'returnRecord.recorder:id,name', 'requests']);

        return Inertia::render('admin/bookings/show', [
            'booking' => BookingResource::make($booking),
            'timeline' => $statuses->timeline($booking),
            'refund' => $booking->status->isCancellable() ? [
                'cancel' => $cancellations->preview($booking)->toArray(),
                'no_show' => $cancellations->preview($booking, noShow: true)->toArray(),
            ] : null,
            'zones' => DeliveryZone::query()->active()->ordered()->get()->map(fn (DeliveryZone $zone) => [
                'value' => (string) $zone->id,
                'label' => $zone->name.' — '.($zone->fee()?->format() ?? 'quoted by staff'),
                'priced' => $zone->fee_minor !== null,
            ]),
            'fuelLevels' => array_map(fn (FuelLevel $level) => ['value' => $level->value, 'label' => $level->label()], FuelLevel::cases()),
        ]);
    }

    public function legalHold(LegalHoldRequest $request, Booking $booking): RedirectResponse
    {
        $hold = $request->boolean('legal_hold');
        $this->desk->setLegalHold($booking, $hold, $request->text('reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => $hold ? 'Legal hold set: KYC will be kept.' : 'Legal hold lifted.']);

        return back();
    }
}
