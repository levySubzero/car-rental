<?php

namespace App\Services\Booking;

use App\Enums\BookingDocument;
use App\Enums\BookingStatus;
use App\Enums\BookingStep;
use App\Exceptions\Domain\BookingException;
use App\Exceptions\Domain\QuoteException;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\CancellationTier;
use App\Models\Car;
use App\Services\Cms\PolicyBundleService;
use App\Services\Media\MediaService;
use App\Services\Pricing\Quote\Quote;
use App\Services\Pricing\QuoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The renter's side of a booking (TECHNICAL_PLAN §11.1): a draft saved step by step, then
 * submitted. Submit re-checks everything, holds the car, freezes the quote and the
 * cancellation tiers (D8, D34) and moves the booking to Awaiting payment.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly DriverEligibilityService $eligibility,
        private readonly BookingStatusService $statuses,
        private readonly BookingReferenceGenerator $references,
        private readonly BookingTokenService $tokens,
        private readonly QuoteService $quotes,
        private readonly PolicyBundleService $bundles,
        private readonly MediaService $media,
    ) {}

    /**
     * Start a draft with the trip from the booking page. The plain token is returned once;
     * only its hash is stored.
     *
     * @param  array<string, mixed>  $trip  SaveTripStepRequest::tripData()
     * @return array{booking: Booking, token: string}
     */
    public function startDraft(Car $car, array $trip, ?string $displayCurrency = null): array
    {
        if (! $car->is_published || ! $car->is_available) {
            throw BookingException::carNotBookable();
        }

        ['token' => $token, 'hash' => $hash] = $this->tokens->issue();

        $booking = new Booking([...$trip, 'car_id' => $car->id, 'display_currency' => $displayCurrency, 'current_step' => BookingStep::Driver]);
        $booking->access_token_hash = $hash;
        $booking->setRelation('car', $car);

        $this->ensureTripWorks($booking);
        $booking->save();

        return ['booking' => $booking, 'token' => $token];
    }

    /**
     * @param  array<string, mixed>  $trip  SaveTripStepRequest::tripData()
     */
    public function saveTrip(Booking $booking, array $trip): Booking
    {
        $this->ensureDraft($booking);

        $booking->fill($trip);
        $this->ensureTripWorks($booking);

        if ($booking->current_step === BookingStep::Trip) {
            $booking->current_step = BookingStep::Driver;
        }

        $booking->save();

        return $booking;
    }

    /**
     * Save the main driver and attach their documents. The details are kept even when they
     * don't qualify yet; the problems come back as field errors and the step stays open.
     *
     * @param  array<string, mixed>  $details  SaveDriverStepRequest::driverData()
     * @param  array<string, string|null>  $documents  media uuid per BookingDocument value
     *
     * @throws ValidationException
     */
    public function saveDriver(Booking $booking, array $details, array $documents): Booking
    {
        $this->ensureDraft($booking);

        $driver = DB::transaction(function () use ($booking, $details, $documents) {
            $driver = $booking->driver ?? new BookingDriver(['booking_id' => $booking->id]);
            $driver->fill($details);
            $driver->booking_id = $booking->id;
            $driver->save();

            $required = $this->eligibility->requirementsFor($driver->id_type, $driver->is_kenyan_resident)['documents'];

            // Documents this driver doesn't need (say, after switching to a passport) are removed.
            foreach (BookingDocument::cases() as $document) {
                $uuid = in_array($document, $required, true) ? ($documents[$document->value] ?? null) : null;
                $this->media->sync($booking, $document->collection(), array_filter([$uuid]), actor: $booking);
            }

            return $driver;
        });

        $booking->setRelation('driver', $driver);
        $errors = $this->problems($booking, $driver);

        $booking->current_step = $errors === [] ? BookingStep::Review : BookingStep::Driver;
        $booking->save();

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $booking;
    }

    /**
     * A fresh quote for the draft as it stands. Delivery is priced only when a leg uses an
     * address; the deposit only once the driver's age is known.
     */
    public function quote(Booking $booking): Quote
    {
        return $this->quotes->quote(
            $booking->car,
            $booking->pickup_at,
            $booking->return_at,
            $booking->extras ?? [],
            $booking->needsDeliveryZone() ? $booking->delivery_zone_id : null,
            $booking->driver?->dob,
        );
    }

    /**
     * Re-check everything, hold the car and freeze the price (M8.15).
     *
     * @throws ValidationException
     */
    public function submit(Booking $booking, string $ip): Booking
    {
        $this->ensureDraft($booking);
        $driver = $booking->driver ?? throw BookingException::stepsIncomplete();

        if ($booking->pickup_at->isPast()) {
            throw BookingException::pickupPassed();
        }

        if (! $booking->car->is_published || ! $booking->car->is_available || $booking->car->trashed()) {
            throw BookingException::carNotBookable();
        }

        $errors = $this->problems($booking, $driver);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $bundle = $this->bundles->current() ?? throw BookingException::noTermsBundle();

        return DB::transaction(function () use ($booking, $bundle, $ip) {
            // A double click submits twice: the second waits here, then finds it submitted.
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraft($locked);

            $quote = $this->quote($booking);
            $hold = $this->availability->lockAndHold($booking);

            $booking->forceFill([
                'reference' => $this->references->next(),
                'quote' => $quote->toArray(),
                'cancellation_tiers' => $this->cancellationTiers(),
                'total_minor' => $quote->total->minor,
                'reservation_minor' => $quote->reservation->minor,
                'balance_minor' => $quote->balance->minor,
                'security_deposit_minor' => $quote->deposit?->minor,
                'policy_bundle_id' => $bundle->id,
                'accepted_terms_at' => now(),
                'accepted_terms_ip' => $ip,
                'submitted_at' => now(),
                'expires_at' => $hold->expires_at,
                'current_step' => BookingStep::Review,
            ])->save();

            return $this->statuses->transition($booking, BookingStatus::AwaitingPayment, 'renter');
        });
    }

    /**
     * Expire stale drafts and unpaid bookings (M8.17). Expiry frees the hold.
     *
     * @return array{drafts: int, unpaid: int}
     */
    public function expireStale(int $draftHours): array
    {
        $counts = ['drafts' => 0, 'unpaid' => 0];

        // A draft lapses that many hours after the renter last saved a step.
        $drafts = Booking::query()->where('status', BookingStatus::Draft)->where('updated_at', '<=', now()->subHours($draftHours));
        // Unpaid: the hold ran out before a payment came, cash was not brought, or a rejected
        // payment was not retried (D38). A payment being checked never lapses.
        $unpaid = Booking::query()
            ->whereIn('status', [BookingStatus::AwaitingPayment, BookingStatus::AwaitingCash, BookingStatus::PaymentRejected])
            ->where('expires_at', '<=', now());

        foreach (['drafts' => $drafts, 'unpaid' => $unpaid] as $kind => $query) {
            $query->lazyById()->each(function (Booking $booking) use ($kind, &$counts) {
                $this->statuses->transition($booking, BookingStatus::Expired, 'system');
                $counts[$kind]++;
            });
        }

        return $counts;
    }

    /**
     * Driver problems that stop the booking: the eligibility rules and the age limits.
     *
     * @return array<string, string>
     */
    private function problems(Booking $booking, BookingDriver $driver): array
    {
        $errors = $this->eligibility->check($booking, $driver);

        try {
            $this->quote($booking);
        } catch (QuoteException $exception) {
            // The quote names the age field `driver_dob`; the driver form calls it `dob`.
            $errors[$exception->field() === 'driver_dob' ? 'dob' : ($exception->field() ?? 'pickup_at')] = $exception->getMessage();
        }

        return $errors;
    }

    /**
     * The trip can be priced and the car is free (not yet held: that happens on submit).
     */
    private function ensureTripWorks(Booking $booking): void
    {
        $this->quote($booking);

        if (! $this->availability->isAvailable($booking->car, $booking->range(), $booking->exists ? $booking : null)) {
            throw BookingException::carUnavailable();
        }
    }

    private function ensureDraft(Booking $booking): void
    {
        if (! $booking->isDraft()) {
            throw BookingException::notEditable();
        }
    }

    /**
     * The tiers in force, longest notice first (D34).
     *
     * @return list<array{hours_before_pickup: int, refund_percent_bp: int, label: string|null}>
     */
    private function cancellationTiers(): array
    {
        return array_values(CancellationTier::query()->orderByDesc('hours_before_pickup')->get()
            ->map(fn (CancellationTier $tier) => [
                'hours_before_pickup' => $tier->hours_before_pickup,
                'refund_percent_bp' => $tier->refund_percent_bp,
                'label' => $tier->label,
            ])->all());
    }
}
