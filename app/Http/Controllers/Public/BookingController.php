<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingDocument;
use App\Enums\IdType;
use App\Exceptions\Domain\QuoteException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveBookingToken;
use App\Http\Requests\Public\Booking\SaveDriverStepRequest;
use App\Http\Requests\Public\Booking\SaveTripStepRequest;
use App\Http\Requests\Public\Booking\StartBookingRequest;
use App\Http\Requests\Public\Booking\SubmitBookingRequest;
use App\Http\Resources\Public\BookingResource;
use App\Http\Resources\Public\CarCardResource;
use App\Http\Resources\Public\QuoteResource;
use App\Models\Booking;
use App\Models\CancellationTier;
use App\Models\Car;
use App\Models\Charge;
use App\Models\DeliveryZone;
use App\Services\Booking\BookingService;
use App\Services\Booking\DriverEligibilityService;
use App\Services\Media\MediaCollectionRegistry;
use App\Services\Pricing\DisplayCurrencyService;
use App\Settings\GeneralSettings;
use App\Settings\PricingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Online booking (TECHNICAL_PLAN §11.1): the booking page starts a draft, the renter's link
 * `/bookings/{token}` shows the wizard while it is a draft and the status page after submit.
 * Payment (steps 4–5) joins in M9.
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function create(Car $car, PricingSettings $pricing): Response
    {
        abort_unless($car->is_published, 404);

        $car->load(['brand', 'carType', 'cover']);

        return Inertia::render('public/booking/start', [
            'car' => CarCardResource::make($car),
            'bookable' => $car->is_available,
            'options' => $this->tripOptions(),
            'reservation_percent' => $pricing->reservation_percent_bp / 100,
            'turnstile_site_key' => config('services.turnstile.site_key'),
        ]);
    }

    public function store(StartBookingRequest $request, Car $car, DisplayCurrencyService $currencies): RedirectResponse
    {
        abort_unless($car->is_published, 404);

        ['token' => $token] = $this->bookings->startDraft(
            $car,
            $request->tripData(),
            $currencies->selected($request->cookie(DisplayCurrencyService::COOKIE)),
        );

        return to_route('bookings.show', $token);
    }

    public function show(Request $request, DriverEligibilityService $eligibility, MediaCollectionRegistry $collections): Response
    {
        $booking = ResolveBookingToken::booking($request)->load(['car.brand', 'car.carType', 'car.cover', 'driver', 'deliveryZone']);
        $token = (string) $request->route('token');

        if (! $booking->isDraft()) {
            return Inertia::render('public/booking/status', [
                'token' => $token,
                'booking' => BookingResource::make($booking),
                'quote' => $this->snapshotQuote($booking),
                'cancellation_tiers' => $booking->cancellation_tiers ?? [],
            ]);
        }

        [$quote, $quoteError] = $this->freshQuote($booking);

        return Inertia::render('public/booking/wizard', [
            'token' => $token,
            'booking' => BookingResource::make($booking),
            'options' => $this->tripOptions(),
            'documents' => $this->documentOptions($eligibility, $collections),
            'quote' => $quote,
            'quote_error' => $quoteError,
            'warnings' => $eligibility->warnings($booking),
            'cancellation_tiers' => CancellationTier::query()->orderByDesc('hours_before_pickup')->get(['hours_before_pickup', 'refund_percent_bp', 'label']),
        ]);
    }

    public function updateTrip(SaveTripStepRequest $request): RedirectResponse
    {
        $this->bookings->saveTrip(ResolveBookingToken::booking($request), $request->tripData());

        return back();
    }

    public function updateDriver(SaveDriverStepRequest $request): RedirectResponse
    {
        $this->bookings->saveDriver($request->booking(), $request->driverData(), $request->documents());

        return back();
    }

    public function submit(SubmitBookingRequest $request): RedirectResponse
    {
        $this->bookings->submit(ResolveBookingToken::booking($request), (string) $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking received. Payment details follow.']);

        return back();
    }

    /**
     * What step 1 offers: delivery zones, extras and the office.
     *
     * @return array<string, mixed>
     */
    private function tripOptions(): array
    {
        $general = app(GeneralSettings::class);

        return [
            'zones' => DeliveryZone::query()->active()->ordered()->get()->map(fn (DeliveryZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'description' => $zone->description,
                'fee' => $zone->fee(),
            ])->values(),
            'extras' => Charge::query()->offeredAsExtra()->ordered()->get()->map(fn (Charge $charge) => [
                'code' => $charge->code,
                'name' => $charge->name,
                'description' => $charge->description,
                'amount' => $charge->amount(),
                'basis' => $charge->basis->value,
                'basis_label' => $charge->basis->label(),
                'payable_at' => $charge->payable_at->label(),
            ])->values(),
            'office' => ['address' => $general->address],
        ];
    }

    /**
     * The document matrix resolved for the form, with each document's upload limits.
     *
     * @return array<string, mixed>
     */
    private function documentOptions(DriverEligibilityService $eligibility, MediaCollectionRegistry $collections): array
    {
        $matrix = [];

        foreach (IdType::cases() as $idType) {
            foreach ([true => 'resident', false => 'non_resident'] as $resident => $key) {
                $rule = $eligibility->requirementsFor($idType, (bool) $resident);
                $matrix[$idType->value][$key] = [
                    'documents' => array_map(fn (BookingDocument $document) => $document->value, $rule['documents']),
                    'kra_pin' => $rule['kra_pin']->value,
                ];
            }
        }

        return [
            'id_types' => array_map(fn (IdType $type) => ['value' => $type->value, 'label' => $type->label()], IdType::cases()),
            'requirements' => $matrix,
            'documents' => array_map(fn (BookingDocument $document) => [
                'value' => $document->value,
                'label' => $document->label(),
                'limits' => $collections->get($document->collection())->toArray(),
            ], BookingDocument::cases()),
        ];
    }

    /**
     * The draft priced now, or why it can't be.
     *
     * @return array{0: QuoteResource|null, 1: string|null}
     */
    private function freshQuote(Booking $booking): array
    {
        try {
            return [QuoteResource::make($this->bookings->quote($booking)), null];
        } catch (QuoteException $exception) {
            return [null, $exception->getMessage()];
        }
    }

    /**
     * The frozen quote, in the shape QuoteResource gives the wizard.
     *
     * @return array<string, mixed>|null
     */
    private function snapshotQuote(Booking $booking): ?array
    {
        $quote = $booking->quote;

        if ($quote === null) {
            return null;
        }

        return [
            'days' => $quote['days'],
            'lines' => array_map(fn (array $line) => array_intersect_key($line, array_flip(['code', 'label', 'quantity', 'unit', 'amount', 'counted', 'note'])), $quote['lines']),
            'total' => $quote['total'],
            'reservation' => $quote['reservation'],
            'balance' => $quote['balance'],
            'deposit' => $quote['deposit'],
            'mileage_km_per_day' => $quote['mileage_km_per_day'],
            'requires_staff_quote' => $quote['requires_staff_quote'],
            'display' => $quote['display'],
        ];
    }
}
