<?php

namespace App\Services\Booking;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\BookingStep;
use App\Enums\HandoverMode;
use App\Enums\PaymentMethodCode;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notifications\BookingNotifier;
use App\Services\Payments\PaymentVerificationService;
use App\Settings\PricingSettings;
use App\Support\DateRange;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Extending a hire (D50). An extension is a booking of its own, attached to the booking the
 * hire started with (the root): same car and driver, running from where the hire now ends to
 * the new return, priced at the margin and paid in full up front. It then goes through the
 * usual steps: a renter's request waits for staff, staff accept it, the renter pays.
 *
 * Once paid, the root's `extended_until` moves (BookingStatusService), the root's voucher
 * shows the extension and the renter is sent it again (BookingNotifier).
 */
class BookingExtensionService
{
    // A hire can be extended once it is confirmed, and while it runs.
    public const EXTENDABLE = [BookingStatus::Reserved, BookingStatus::Active];

    public const PAY_LINK = 'link';

    public const PAY_RECORDED = 'paid';

    public function __construct(
        private readonly ExtensionPricer $pricer,
        private readonly AvailabilityService $availability,
        private readonly BookingAcceptanceService $acceptance,
        private readonly BookingQuoteAdjuster $adjuster,
        private readonly BookingReferenceGenerator $references,
        private readonly BookingService $bookings,
        private readonly BookingStatusService $statuses,
        private readonly BookingTokenService $tokens,
        private readonly BookingNotifier $notifier,
        private readonly PaymentVerificationService $payments,
        private readonly PricingSettings $pricing,
    ) {}

    /**
     * What extending to $newReturn would cost, for the renter's and staff's forms. Nothing is saved.
     *
     * @return array{quote: array<string, mixed>, available: bool}
     */
    public function preview(Booking $root, CarbonImmutable $newReturn): array
    {
        $this->ensureExtendable($root, $newReturn);
        $quote = $this->pricer->quote($root, $newReturn);

        return [
            'quote' => $quote->toArray(),
            'available' => $this->availability->isAvailable($root->car, new DateRange($quote->from, $quote->to), $root),
        ];
    }

    /**
     * The renter asks for more days from their booking page or the portal. It holds no car
     * until staff accept it, just like a new request.
     */
    public function request(Booking $root, CarbonImmutable $newReturn, ?string $note = null): Booking
    {
        ['token' => $token, 'hash' => $hash] = $this->tokens->issue();

        $extension = DB::transaction(function () use ($root, $newReturn, $note, $hash) {
            $root = $this->lockRoot($root);
            $this->ensureExtendable($root, $newReturn);
            $quote = $this->pricer->quote($root, $newReturn);

            if (! $this->availability->isAvailable($root->car, new DateRange($quote->from, $quote->to), $root)) {
                throw BookingException::carUnavailable();
            }

            $extension = $this->make($root, $quote, BookingSource::Website, $hash);
            $extension->forceFill(['expires_at' => $this->decideBy($extension)])->save();
            $this->statuses->transition($extension, BookingStatus::Requested, 'renter', $note);

            $this->log($root, $extension, null, 'extension-requested', 'extension requested by the renter', ['note' => $note]);

            return $extension;
        });

        $this->notifier->submitted($extension, $token);

        return $extension;
    }

    /**
     * Staff extend a hire themselves. With a payment link the renter is sent the link to pay,
     * exactly as for an accepted request; with a payment recorded the extension is confirmed
     * on the spot.
     *
     * @param  self::PAY_*  $payment
     */
    public function create(
        Booking $root, CarbonImmutable $newReturn, ?Money $total, string $payment, User $actor,
        ?Money $paid = null, ?PaymentMethodCode $method = null, ?string $reference = null,
    ): Booking {
        return DB::transaction(function () use ($root, $newReturn, $total, $payment, $actor, $paid, $method, $reference) {
            $root = $this->lockRoot($root);
            $this->ensureExtendable($root, $newReturn);
            $quote = $this->pricer->quote($root, $newReturn);

            $extension = $this->make($root, $quote, BookingSource::Admin, $this->tokens->issue()['hash']);
            $this->log($root, $extension, $actor, 'extension-created', 'extension entered by staff', ['payment' => $payment]);

            if ($payment === self::PAY_LINK) {
                $this->statuses->transition($extension, BookingStatus::Requested, $actor, 'Entered by staff');

                // Holds the car and sends the renter the link to pay.
                return $this->acceptance->accept($extension, $total, null, $actor);
            }

            $this->availability->lockAndHold($extension);
            $extension->refresh();
            $this->adjuster->adjust($extension, $total, null);
            $extension->save();

            $this->payments->recordOffline($extension, $paid ?? new Money((int) $extension->total_minor), $method, $reference, $actor);

            return $extension->refresh();
        });
    }

    public function ensureExtendable(Booking $root, CarbonImmutable $newReturn): void
    {
        if ($root->isExtension()) {
            throw BookingException::extensionOfExtension();
        }

        if (! in_array($root->status, self::EXTENDABLE, true)) {
            throw BookingException::notExtendable($root->status);
        }

        $pending = $root->extensions()->whereIn('status', Booking::PENDING_EXTENSION)->first();

        if ($pending !== null) {
            throw BookingException::extensionPending((string) $pending->reference);
        }

        if (! $newReturn->isAfter($root->chainEnd())) {
            throw BookingException::extensionTooShort();
        }
    }

    private function lockRoot(Booking $root): Booking
    {
        $locked = Booking::query()->whereKey($root->id)->lockForUpdate()->firstOrFail();
        $locked->load(['car', 'driver']);

        return $locked;
    }

    /**
     * The extension booking, still a draft: the root's car, driver and terms, the stay from
     * where the hire ends to the new return, and the marginal price, all due up front. The car
     * changes hands only once each way, on the root, so the extension has no hand-over of its own.
     */
    private function make(Booking $root, ExtensionQuote $quote, BookingSource $source, string $tokenHash): Booking
    {
        $extension = new Booking([
            'car_id' => $root->car_id,
            'pickup_at' => $quote->from,
            'return_at' => $quote->to,
            'pickup_mode' => HandoverMode::Office,
            'return_mode' => HandoverMode::Office,
            'extras' => $root->extras ?? [],
            'display_currency' => $root->display_currency,
            'current_step' => BookingStep::Review,
        ]);

        $extension->forceFill([
            'root_booking_id' => $root->id,
            'access_token_hash' => $tokenHash,
            'source' => $source,
            'reference' => $this->references->next(),
            'quote' => $quote->snapshot,
            'cancellation_tiers' => $root->cancellation_tiers ?? $this->bookings->cancellationTiers(),
            'policy_bundle_id' => $root->policy_bundle_id,
            'total_minor' => $quote->amount->minor,
            'reservation_minor' => $quote->amount->minor,
            'balance_minor' => 0,
            'security_deposit_minor' => null,
            'submitted_at' => now(),
        ])->save();

        if ($root->driver !== null) {
            $driver = $root->driver->replicate();
            $driver->booking_id = $extension->id;
            $driver->save();
        }

        $extension->setRelation('car', $root->car);
        $extension->setRelation('root', $root);

        // The array cast turns the snapshot's Money objects into plain data.
        return $extension->refresh();
    }

    /**
     * How long staff have to answer a renter's request: until the hire would have ended, or a
     * payment window when that is (nearly) now.
     */
    private function decideBy(Booking $extension): CarbonImmutable
    {
        $window = CarbonImmutable::now()->addMinutes($this->pricing->hold_minutes);

        return $extension->pickup_at->isAfter($window) ? $extension->pickup_at : $window;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Booking $root, Booking $extension, ?User $actor, string $event, string $description, array $properties = []): void
    {
        foreach ([$root, $extension] as $subject) {
            activity(BookingDeskService::LOG_NAME)->performedOn($subject)->causedBy($actor)
                ->withProperties([
                    ...$properties,
                    'root' => $root->reference,
                    'extension' => $extension->reference,
                    'return_at' => $extension->return_at->toIso8601String(),
                    'total_minor' => $extension->total_minor,
                ])
                ->event($event)
                ->log($description);
        }
    }
}
