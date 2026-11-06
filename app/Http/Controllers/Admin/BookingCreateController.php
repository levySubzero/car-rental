<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChargeApplies;
use App\Enums\HandoverMode;
use App\Enums\PaymentMethodCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\StoreOfflineBookingRequest;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Charge;
use App\Models\DeliveryZone;
use App\Models\PaymentMethod;
use App\Services\Booking\OfflineBookingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entering a booking for a client who did not book online, then on to their voucher.
 */
class BookingCreateController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Booking::class);

        return Inertia::render('admin/bookings/create', [
            'cars' => Car::query()->orderBy('name')->get(['id', 'name'])->map(fn (Car $car) => ['value' => (string) $car->id, 'label' => $car->name]),
            'zones' => DeliveryZone::query()->active()->ordered()->get()->map(fn (DeliveryZone $zone) => ['value' => (string) $zone->id, 'label' => $zone->name]),
            'extras' => Charge::query()->where('is_active', true)->where('applies', ChargeApplies::Extra)->get()->map(fn (Charge $charge) => ['value' => $charge->code, 'label' => $charge->name]),
            'modes' => array_map(fn (HandoverMode $mode) => ['value' => $mode->value, 'label' => $mode->label()], HandoverMode::cases()),
            'methods' => PaymentMethod::query()->ordered()->get()->map(fn (PaymentMethod $method) => ['value' => $method->code->value, 'label' => $method->name]),
        ]);
    }

    public function store(StoreOfflineBookingRequest $request, OfflineBookingService $offline): RedirectResponse
    {
        $booking = $offline->create(
            Car::query()->findOrFail($request->integer('car_id')),
            $request->tripData(),
            [
                'first_name' => (string) $request->validated('first_name'),
                'surname' => (string) $request->validated('surname'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
            ],
            $request->money('total'),
            $request->money('deposit'),
            $request->money('paid') ?? new Money(0),
            $request->enum('payment_method', PaymentMethodCode::class),
            $request->validated('payment_reference'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$booking->reference} is booked and the car is blocked. Review the voucher."]);

        return to_route('admin.bookings.voucher.show', $booking->reference);
    }
}
