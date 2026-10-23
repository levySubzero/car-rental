<?php

use App\Enums\AvailabilityBlockType;
use App\Enums\BookingDocument;
use App\Enums\BookingStatus;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\CancellationTier;
use App\Models\Car;
use App\Models\Charge;
use App\Models\DeliveryZone;
use App\Models\PolicyBundle;
use App\Notifications\Booking\BookingReceived;
use App\Services\Booking\BookingStatusService;
use App\Services\Booking\BookingTokenService;
use App\Settings\PricingSettings;
use Database\Seeders\PricingSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;
use Tests\Support\MediaFixtures;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));
    $this->car = Car::factory()->published()->create(['daily_rate_minor' => 1_500_000, 'car_value_minor' => 800_000_000]);
    $this->bundle = PolicyBundle::create(['version' => 1, 'published_at' => now()]);
    $this->nairobi = DeliveryZone::firstWhere('name', 'Nairobi');
});

// The PRD worked example: 3 days at KES 15,000 with a chauffeur, delivered in Nairobi.
function tripPayload(array $overrides = []): array
{
    return [
        'pickup_at' => '2026-11-02T10:00:00+03:00',
        'return_at' => '2026-11-04T10:00:00+03:00',
        'pickup_mode' => 'address',
        'return_mode' => 'office',
        'delivery_zone_id' => test()->nairobi->id,
        'delivery_address' => 'Riverside Drive, Westlands',
        'delivery_landmark' => 'Opposite the petrol station',
        'delivery_contact_phone' => '+254 700 000 001',
        'extras' => ['chauffeur'],
        'flight_number' => null,
        ...$overrides,
    ];
}

function driverPayload(array $documents = [], array $overrides = []): array
{
    return [
        'first_name' => 'Amina',
        'surname' => 'Otieno',
        'dob' => '1991-03-15',
        'email' => 'amina@example.test',
        'phone' => '+254 700 000 002',
        'nationality' => 'Kenyan',
        'is_kenyan_resident' => true,
        'id_type' => 'national_id',
        'id_number' => '12345678',
        'kra_pin' => 'A123456789Z',
        'licence_number' => 'DL-1234567',
        'licence_expiry' => '2029-01-01',
        'licence_years_declared' => 8,
        'documents' => $documents,
        ...$overrides,
    ];
}

/**
 * Start a draft through the booking page and return its plain token.
 */
function startBooking(array $trip = []): string
{
    $response = test()->post(route('bookings.store', test()->car), tripPayload($trip))->assertSessionHasNoErrors();
    $location = $response->headers->get('Location');

    return basename((string) $location);
}

/**
 * Uploads every document a Kenyan resident needs with the booking token.
 *
 * @return array<string, string>
 */
function uploadDocuments(string $token, ?array $documents = null): array
{
    $documents ??= [BookingDocument::IdFront, BookingDocument::IdBack, BookingDocument::KraCertificate, BookingDocument::Licence];
    $uuids = [];

    foreach ($documents as $document) {
        $uuids[$document->value] = test()
            ->withHeaders(['Accept' => 'application/json', 'X-Booking-Token' => $token])
            ->post(route('media.store'), ['collection' => $document->collection(), 'file' => MediaFixtures::pdf()])
            ->assertCreated()
            ->json('uuid');
    }

    test()->flushHeaders();

    return $uuids;
}

it('shows the booking page for a published car only', function () {
    $this->get(route('bookings.create', $this->car))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/booking/start')
            ->where('bookable', true)
            ->has('options.zones', 2)
            ->where('options.extras.0.code', 'chauffeur'));

    $this->car->forceFill(['is_published' => false])->save();
    $this->get(route('bookings.create', $this->car))->assertNotFound();
});

it('takes a renter from the booking page to awaiting payment', function () {
    CancellationTier::factory()->create(['hours_before_pickup' => 72, 'refund_percent_bp' => 5000, 'label' => null]);

    $token = startBooking();
    $booking = app(BookingTokenService::class)->find($token);

    expect($booking->status)->toBe(BookingStatus::Draft)
        ->and($booking->current_step->value)->toBe('driver')
        ->and($booking->delivery_address)->toBe('Riverside Drive, Westlands')
        ->and($booking->return_address)->toBeNull();

    $this->get(route('bookings.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/booking/wizard')
            ->where('booking.current_step', 'driver')
            ->where('quote.total.major', '66350.00')
            ->has('documents.requirements.passport.non_resident'));

    $this->put(route('bookings.driver', $token), driverPayload(uploadDocuments($token)))->assertSessionHasNoErrors();
    expect($booking->fresh()->current_step->value)->toBe('review');

    Notification::fake();
    $this->post(route('bookings.submit', $token), ['accept_terms' => true])->assertSessionHasNoErrors();

    Notification::assertSentTo(new AnonymousNotifiable, BookingReceived::class, fn (BookingReceived $notification, array $channels, AnonymousNotifiable $notifiable) => $notification->token === $token
        && $notifiable->routes['mail'] === 'amina@example.test');

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($booking->reference)->toBe('BE-2026-000001')
        ->and($booking->total_minor)->toBe(6_635_000)
        ->and($booking->reservation_minor)->toBe(1_658_800)
        ->and($booking->balance_minor)->toBe(4_976_200)
        ->and($booking->security_deposit_minor)->toBe(16_000_000)
        ->and($booking->policy_bundle_id)->toBe($this->bundle->id)
        ->and($booking->accepted_terms_ip)->toBe('127.0.0.1')
        ->and($booking->cancellation_tiers)->toBe([['hours_before_pickup' => 72, 'refund_percent_bp' => 5000, 'label' => null]])
        ->and($booking->quote['settings_hash'])->toBeString()
        ->and($booking->expires_at->toIso8601String())->toBe(now()->addMinutes(120)->toIso8601String())
        ->and($booking->mediaIn('booking.licence')->count())->toBe(1);

    $hold = $booking->availabilityBlocks()->sole();
    expect($hold->type)->toBe(AvailabilityBlockType::Hold);

    $this->get(route('bookings.show', $token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/booking/status')
            ->where('booking.reference', 'BE-2026-000001')
            ->where('booking.status', 'awaiting_payment')
            ->where('quote.reservation.major', '16588.00')
            ->where('cancellation_tiers.0.refund_percent_bp', 5000)
            ->missing('booking.documents'));

    expect(app(BookingStatusService::class)->timeline($booking))->toHaveCount(1);
});

it('needs a valid token', function () {
    $this->get(route('bookings.show', str_repeat('x', 64)))->assertNotFound();
    $this->put(route('bookings.trip', 'short'), tripPayload())->assertNotFound();
});

it('checks Turnstile and the start limit', function () {
    config()->set('services.turnstile.secret_key', null);
    RateLimiter::clear('booking-start');

    foreach (range(1, 10) as $attempt) {
        $this->post(route('bookings.store', $this->car), tripPayload(['pickup_at' => 'nope']));
    }

    $this->post(route('bookings.store', $this->car), tripPayload())->assertTooManyRequests();
});

it('validates the trip and the address legs', function (array $input, string $field) {
    $this->post(route('bookings.store', $this->car), tripPayload($input))->assertSessionHasErrors($field);
})->with([
    'past pickup' => [['pickup_at' => '2026-10-01T10:00:00+03:00'], 'pickup_at'],
    'return before pickup' => [['return_at' => '2026-11-01T10:00:00+03:00'], 'return_at'],
    'no zone for a delivery' => [['delivery_zone_id' => null], 'delivery_zone_id'],
    'no address' => [['delivery_address' => ''], 'delivery_address'],
    'no driver phone' => [['delivery_contact_phone' => ''], 'delivery_contact_phone'],
    'collection needs an address' => [['return_mode' => 'address'], 'return_address'],
    'unknown extra' => [['extras' => ['jetpack']], 'extras.0'],
]);

it('drops the address of an office leg and the zone when nothing is delivered', function () {
    $token = startBooking(['pickup_mode' => 'office', 'delivery_address' => 'Ignored']);
    $booking = app(BookingTokenService::class)->find($token);

    expect($booking->delivery_address)->toBeNull()
        ->and($booking->delivery_zone_id)->toBeNull();
});

it('refuses dates the car is already taken for', function () {
    AvailabilityBlock::factory()->create(['car_id' => $this->car->id, 'starts_at' => '2026-11-03 00:00', 'ends_at' => '2026-11-05 00:00']);

    $this->from(route('bookings.create', $this->car))
        ->post(route('bookings.store', $this->car), tripPayload())
        ->assertRedirect(route('bookings.create', $this->car));

    expect(Booking::count())->toBe(0);
});

it('keeps the driver details but reports missing documents, age and licence problems', function (array $overrides, array $documents, array $fields) {
    $token = startBooking();
    $uploaded = uploadDocuments($token);
    $documents = array_intersect_key($uploaded, array_flip($documents));

    $this->put(route('bookings.driver', $token), driverPayload($documents, $overrides))->assertSessionHasErrors($fields);

    $booking = app(BookingTokenService::class)->find($token);
    expect($booking->driver)->not->toBeNull()
        ->and($booking->current_step->value)->toBe('driver');
})->with([
    'missing documents' => [[], ['id_front'], ['documents.id_back', 'documents.kra_certificate', 'documents.licence']],
    'under age' => [['dob' => '2005-01-01'], ['id_front', 'id_back', 'kra_certificate', 'licence'], ['dob']],
    'licence ends before return' => [['licence_expiry' => '2026-11-03'], ['id_front', 'id_back', 'kra_certificate', 'licence'], ['licence_expiry']],
    'too few licence years' => [['licence_years_declared' => 1], ['id_front', 'id_back', 'kra_certificate', 'licence'], ['licence_years_declared']],
    'no KRA PIN' => [['kra_pin' => ''], ['id_front', 'id_back', 'kra_certificate', 'licence'], ['kra_pin']],
]);

it('asks a passport holder only for the passport and licence, and drops the other files', function () {
    $token = startBooking();
    $resident = uploadDocuments($token);
    $this->put(route('bookings.driver', $token), driverPayload($resident))->assertSessionHasNoErrors();

    $passport = uploadDocuments($token, [BookingDocument::Passport]);
    $this->put(route('bookings.driver', $token), driverPayload(
        [...$resident, ...$passport],
        ['id_type' => 'passport', 'is_kenyan_resident' => false, 'nationality' => 'British', 'kra_pin' => '', 'id_number' => '', 'licence_number' => ''],
    ))->assertSessionHasNoErrors();

    $booking = app(BookingTokenService::class)->find($token);

    expect($booking->media()->pluck('collection')->sort()->values()->all())->toBe(['booking.licence', 'booking.passport'])
        // Blank identity numbers keep the saved ones.
        ->and($booking->driver->id_number)->toBe('12345678');
});

it('never sends identity numbers back to the browser', function () {
    $token = startBooking();
    $this->put(route('bookings.driver', $token), driverPayload(uploadDocuments($token)));

    $response = $this->get(route('bookings.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('booking.driver.id_number_hint', '•••••678'));

    expect($response->getContent())->not->toContain('12345678')
        ->not->toContain('A123456789Z')
        ->not->toContain('DL-1234567');
});

it('refuses another file uploaded by someone else', function () {
    $token = startBooking();
    $other = startBooking(['pickup_at' => '2026-12-02T10:00:00+03:00', 'return_at' => '2026-12-04T10:00:00+03:00']);
    $theirs = uploadDocuments($other, [BookingDocument::IdFront]);

    $this->put(route('bookings.driver', $token), driverPayload($theirs))->assertSessionHasErrors('documents.id_front');
});

it('lets only one of two renters submit the same dates', function () {
    $first = startBooking();
    $second = startBooking(['pickup_at' => '2026-11-03T10:00:00+03:00', 'return_at' => '2026-11-05T10:00:00+03:00']);

    foreach ([$first, $second] as $token) {
        $this->put(route('bookings.driver', $token), driverPayload(uploadDocuments($token)))->assertSessionHasNoErrors();
    }

    $this->post(route('bookings.submit', $first), ['accept_terms' => true])->assertSessionHasNoErrors();
    $this->post(route('bookings.submit', $second), ['accept_terms' => true]);

    expect(app(BookingTokenService::class)->find($second)->status)->toBe(BookingStatus::Draft)
        ->and(AvailabilityBlock::count())->toBe(1)
        ->and(Booking::whereNotNull('reference')->count())->toBe(1);
});

it('submits once, refuses edits afterwards, and needs the terms accepted', function () {
    $token = startBooking();
    $this->put(route('bookings.driver', $token), driverPayload(uploadDocuments($token)));

    $this->post(route('bookings.submit', $token), [])->assertSessionHasErrors('accept_terms');

    $this->post(route('bookings.submit', $token), ['accept_terms' => true]);
    $this->post(route('bookings.submit', $token), ['accept_terms' => true]);
    $this->put(route('bookings.trip', $token), tripPayload());

    expect(Booking::sole()->reference)->toBe('BE-2026-000001')
        ->and(AvailabilityBlock::count())->toBe(1);

    $this->withHeaders(['Accept' => 'application/json', 'X-Booking-Token' => $token])
        ->post(route('media.store'), ['collection' => 'booking.licence', 'file' => MediaFixtures::pdf()])
        ->assertForbidden();
});

it('books a staff-quoted zone with delivery left out of the reservation', function () {
    $outside = DeliveryZone::firstWhere('name', 'Outside Nairobi');
    $token = startBooking(['delivery_zone_id' => $outside->id, 'extras' => []]);
    $this->put(route('bookings.driver', $token), driverPayload(uploadDocuments($token)));
    $this->post(route('bookings.submit', $token), ['accept_terms' => true])->assertSessionHasNoErrors();

    $booking = Booking::sole();
    $delivery = collect($booking->quote['lines'])->firstWhere('kind', 'delivery');

    expect($booking->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($booking->quote['requires_staff_quote'])->toBeTrue()
        ->and($delivery['amount'])->toBeNull()
        ->and($delivery['counted'])->toBeFalse()
        // 3 days × 15,000; 25% reserved.
        ->and($booking->total_minor)->toBe(4_500_000)
        ->and($booking->reservation_minor)->toBe(1_125_000);
});

it('keeps the snapshot when settings, rates, charges or tiers change', function () {
    $token = startBooking();
    $this->put(route('bookings.driver', $token), driverPayload(uploadDocuments($token)));
    $this->post(route('bookings.submit', $token), ['accept_terms' => true]);
    $before = Booking::sole()->only(['quote', 'cancellation_tiers', 'total_minor', 'reservation_minor', 'balance_minor']);

    $pricing = app(PricingSettings::class);
    $pricing->reservation_percent_bp = 5000;
    $pricing->save();
    PricingFixtures::rates(['USD' => '150']);
    Charge::query()->update(['amount_minor' => 99_900]);
    CancellationTier::factory()->create(['hours_before_pickup' => 24, 'refund_percent_bp' => 10000]);
    $this->car->update(['daily_rate_minor' => 9_900_000]);

    expect(Booking::sole()->only(array_keys($before)))->toBe($before);

    $this->get(route('bookings.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('quote.total.major', '66350.00')->where('cancellation_tiers', []));
});

it('expires stale drafts and unpaid bookings, freeing the car', function () {
    $draft = startBooking();
    $unpaid = startBooking(['pickup_at' => '2026-12-02T10:00:00+03:00', 'return_at' => '2026-12-04T10:00:00+03:00']);
    $this->put(route('bookings.driver', $unpaid), driverPayload(uploadDocuments($unpaid)));
    $this->post(route('bookings.submit', $unpaid), ['accept_terms' => true]);

    $this->travel(119)->minutes();
    $this->artisan('bookings:expire')->assertSuccessful();
    expect(Booking::where('status', 'expired')->count())->toBe(0);

    $this->travel(2)->minutes();
    $this->artisan('bookings:expire')->assertSuccessful();
    expect(app(BookingTokenService::class)->find($unpaid)->status)->toBe(BookingStatus::Expired)
        ->and(AvailabilityBlock::count())->toBe(0)
        ->and(app(BookingTokenService::class)->find($draft)->status)->toBe(BookingStatus::Draft);

    $this->travel(24)->hours();
    $this->artisan('bookings:expire')->assertSuccessful();
    expect(app(BookingTokenService::class)->find($draft)->status)->toBe(BookingStatus::Expired);
});

it('stores the renter\'s Nairobi times in UTC', function () {
    $booking = app(BookingTokenService::class)->find(startBooking());

    expect($booking->getRawOriginal('pickup_at'))->toBe('2026-11-02 07:00:00')
        ->and($booking->pickup_at->toIso8601String())->toBe('2026-11-02T07:00:00+00:00');
});

it('keeps booking links out of search engines', function () {
    $this->get(route('robots'))->assertSee('Disallow: /bookings');
});
