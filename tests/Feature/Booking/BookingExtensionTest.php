<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Enums\PaymentMethodCode;
use App\Enums\Permission;
use App\Exceptions\Domain\BookingException;
use App\Http\Middleware\EnsurePortalSession;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\Car;
use App\Notifications\Booking\BookingAccepted;
use App\Notifications\Booking\BookingExtended;
use App\Notifications\Booking\BookingReceived;
use App\Services\Booking\BookingDeskService;
use App\Services\Booking\BookingExtensionService;
use App\Services\Booking\BookingService;
use App\Services\Booking\CancellationService;
use App\Services\Booking\HandoverService;
use App\Services\Notifications\VoucherService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Pricing\QuoteService;
use App\Settings\PricingSettings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\PricingSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BookingFixtures;
use Tests\Support\PricingFixtures;

function nairobi(string $local): CarbonImmutable
{
    return CarbonImmutable::parse($local, 'Africa/Nairobi')->utc();
}

beforeEach(function () {
    BookingFixtures::setUp();
    Notification::fake();
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    $this->travelTo(nairobi('2026-11-25 09:00'));

    // Two hours between hires: the extension starts right where the root ends, inside that gap.
    $pricing = app(PricingSettings::class);
    $pricing->turnaround_minutes = 120;
    $pricing->save();

    $this->service = app(BookingExtensionService::class);
    $this->staff = staffWith([Permission::BookingsView, Permission::BookingsExtend, Permission::BookingsAccept, Permission::PaymentsVerify]);
    $this->car = Car::factory()->create(['daily_rate_minor' => 420_000, 'car_value_minor' => 200_000_000]);
    $this->token = str_repeat('x', (int) config('baines.booking_token_length'));

    $this->root = Booking::factory()->reserved()->for($this->car)->withToken($this->token)->create([
        'pickup_at' => nairobi('2026-12-05 12:30'),
        'return_at' => nairobi('2026-12-13 11:30'),
        'security_deposit_minor' => 4_400_000,
    ]);
    $this->root->availabilityBlocks()->update(['starts_at' => $this->root->pickup_at, 'ends_at' => $this->root->return_at]);
    BookingDriver::factory()->for($this->root)->create(['first_name' => 'Amina', 'email' => 'amina@example.test', 'phone' => '0712 345 678', 'dob' => '1990-01-01']);

    $quote = app(BookingService::class)->quote($this->root->fresh());
    $this->root->forceFill([
        'quote' => $quote->toArray(),
        'total_minor' => $quote->total->minor,
        'reservation_minor' => $quote->reservation->minor,
        'reservation_paid_minor' => $quote->reservation->minor,
        'balance_minor' => $quote->balance->minor,
    ])->save();
    $this->root->refresh();
});

/**
 * The marginal price: the whole longer stay less the stay as it stands, both at today's rates.
 */
function marginal(Booking $root, CarbonImmutable $from, CarbonImmutable $to): int
{
    $quotes = app(QuoteService::class);
    $dob = $root->driver->dob;

    return $quotes->quote($root->car, $root->pickup_at, $to, [], null, $dob)->total->minor
        - $quotes->quote($root->car, $root->pickup_at, $from, [], null, $dob)->total->minor;
}

describe('pricing', function () {
    it('prices the extra days at the margin, with no deposit, and saves nothing', function () {
        $preview = $this->service->preview($this->root, nairobi('2026-12-15 11:30'));

        expect($preview['quote']['extra_days'])->toBe(2)
            ->and($preview['quote']['chain_days'])->toBe((int) $this->root->quote['days'] + 2)
            ->and($preview['quote']['amount']->minor)->toBe(marginal($this->root, $this->root->return_at, nairobi('2026-12-15 11:30')))
            ->and($preview['quote']['amount']->minor)->toBe(2 * 420_000)
            ->and($preview['available'])->toBeTrue()
            ->and(Booking::query()->count())->toBe(1);
    });

    it('does not charge the delivery again', function () {
        $this->root->forceFill(['delivery_fee_minor' => 500_000])->save();

        expect($this->service->preview($this->root, nairobi('2026-12-15 11:30'))['quote']['amount']->minor)->toBe(2 * 420_000);
    });

    it('freezes the extra days on the extension, paid in full up front', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);

        expect($extension->root_booking_id)->toBe($this->root->id)
            ->and($extension->pickup_at->equalTo($this->root->return_at))->toBeTrue()
            ->and($extension->return_at->equalTo(nairobi('2026-12-15 11:30')))->toBeTrue()
            ->and($extension->total_minor)->toBe(840_000)
            ->and($extension->reservation_minor)->toBe(840_000)
            ->and($extension->balance_minor)->toBe(0)
            ->and($extension->security_deposit_minor)->toBeNull()
            ->and($extension->quote['days'])->toBe(2)
            ->and($extension->quote['extension_of'])->toBe($this->root->reference)
            ->and($extension->quote['lines'][0]['label'])->toBe('Extra rental (2 days)')
            ->and($extension->driver->email)->toBe('amina@example.test')
            ->and($extension->source)->toBe(BookingSource::Admin);
    });

    it('keeps a price staff typed in as the amount due', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), Money::fromMajor('7000'), BookingExtensionService::PAY_LINK, $this->staff);

        expect($extension->total_minor)->toBe(700_000)
            ->and($extension->reservation_minor)->toBe(700_000)
            ->and($extension->balance_minor)->toBe(0);
    });
});

describe('availability', function () {
    it('never clashes with the hire it extends, turnaround included', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);

        expect($extension->status)->toBe(BookingStatus::AwaitingPayment)
            ->and($extension->availabilityBlocks()->sole()->ends_at->equalTo(nairobi('2026-12-15 11:30')))->toBeTrue();
    });

    it('refuses days another booking holds', function () {
        AvailabilityBlock::factory()->create([
            'car_id' => $this->car->id,
            'starts_at' => nairobi('2026-12-14 12:00'),
            'ends_at' => nairobi('2026-12-16 12:00'),
        ]);

        expect($this->service->preview($this->root, nairobi('2026-12-15 11:30'))['available'])->toBeFalse()
            ->and(fn () => $this->service->request($this->root, nairobi('2026-12-15 11:30')))->toThrow(BookingException::class);
    });
});

describe('rules', function () {
    it('takes one extension at a time, each from where the hire then ends, and only on the root', function () {
        $first = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);

        expect(fn () => $this->service->preview($this->root->fresh(), nairobi('2026-12-17 11:30')))
            ->toThrow(BookingException::class, "Extension {$first->reference} is still waiting");

        app(PaymentVerificationService::class)->markPaidByStaff($first, new Money(840_000), PaymentMethodCode::Cash, null, $this->staff);
        $root = $this->root->fresh();

        $second = $this->service->create($root, nairobi('2026-12-17 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);

        expect($second->root_booking_id)->toBe($root->id)
            ->and($second->pickup_at->equalTo(nairobi('2026-12-15 11:30')))->toBeTrue()
            ->and(fn () => $this->service->preview($second, nairobi('2026-12-19 11:30')))->toThrow(BookingException::class, 'Extend the original booking')
            ->and(fn () => $this->service->preview($root, nairobi('2026-12-14 11:30')))->toThrow(BookingException::class);
    });

    it('only extends confirmed or running hires', function () {
        $this->root->forceFill(['status' => BookingStatus::Completed])->save();

        $this->service->preview($this->root, nairobi('2026-12-15 11:30'));
    })->throws(BookingException::class, 'cannot be extended');
});

describe('confirmation', function () {
    it('moves the hire\'s end, puts the extension on the root\'s voucher and sends it to the renter', function () {
        app(VoucherService::class)->generate($this->root);
        $this->root->forceFill(['voucher_overrides' => ['return_date' => 'Sunday 13 December 2026, late', 'area_of_use' => 'Kenya only']])->save();

        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);
        Notification::assertSentTo(new AnonymousNotifiable, BookingAccepted::class, fn (BookingAccepted $notification) => $notification->booking->is($extension));

        app(PaymentVerificationService::class)->markPaidByStaff($extension, new Money(840_000), PaymentMethodCode::Cash, 'R1', $this->staff);

        $root = $this->root->fresh();
        $html = app(VoucherService::class)->html($root);

        expect($extension->fresh()->status)->toBe(BookingStatus::Reserved)
            ->and($root->extended_until->equalTo(nairobi('2026-12-15 11:30')))->toBeTrue()
            ->and($root->voucher_overrides)->toBe(['area_of_use' => 'Kenya only'])
            ->and($html)->toContain($extension->reference)
            ->and($html)->toContain('Tuesday 15 December 2026')
            ->and($html)->toContain('Total including extensions')
            ->and($html)->toContain('Kenya only')
            ->and(app(VoucherService::class)->html($extension))->toBe($html);

        Notification::assertSentTo(new AnonymousNotifiable, BookingExtended::class, function (BookingExtended $notification, array $channels, $notifiable) use ($extension, $root) {
            $mail = $notification->toMail($notifiable);
            $lines = implode("\n", $mail->introLines);

            return $notification->booking->is($root)
                && $notification->extension->is($extension)
                && $channels === ['mail']
                && count($mail->rawAttachments) + count($mail->attachments) === 1
                && str_contains($lines, '**Area of use:** Kenya only')
                && str_contains($lines, '**Extra days:** 2');
        });
    });

    it('takes a payment staff recorded and puts it straight on hire when the car is already out', function () {
        $this->root->forceFill(['status' => BookingStatus::Active])->save();

        $extension = $this->service->create(
            $this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_RECORDED, $this->staff,
            new Money(840_000), PaymentMethodCode::Cash, 'CASH-1',
        );

        expect($extension->status)->toBe(BookingStatus::Active)
            ->and($extension->reservation_paid_minor)->toBe(840_000)
            ->and($extension->balance_minor)->toBe(0)
            ->and($this->root->fresh()->chainEnd()->equalTo(nairobi('2026-12-15 11:30')))->toBeTrue();
    });

    it('accepts an extension even after the hire\'s original return has passed', function () {
        $this->root->forceFill(['status' => BookingStatus::Active])->save();
        $this->travelTo(nairobi('2026-12-13 15:00'));

        $extension = $this->service->request($this->root, nairobi('2026-12-15 11:30'));
        $this->actingAs($this->staff)->post(route('admin.bookings.accept', $extension->reference))->assertSessionHasNoErrors();

        expect($extension->fresh()->status)->toBe(BookingStatus::AwaitingPayment)
            ->and($extension->fresh()->expires_at->isFuture())->toBeTrue();
    });
});

describe('the hire around it', function () {
    it('hands extensions over and takes them back with the root, counting all the days for mileage', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_RECORDED, $this->staff, new Money(840_000), PaymentMethodCode::Cash);
        expect($extension->status)->toBe(BookingStatus::Reserved);

        $handovers = app(HandoverService::class);
        $handovers->recordHandover($this->root->fresh(), 1000, FuelLevel::Full, new Money((int) $this->root->balance_minor), new Money(0), null, $this->staff);
        expect($extension->fresh()->status)->toBe(BookingStatus::Active);

        expect(fn () => $handovers->recordReturn($extension->fresh(), 2000, FuelLevel::Full, new Money(0), new Money(0), null, $this->staff))
            ->toThrow(BookingException::class, 'This is an extension');

        $days = (int) $extension->fresh()->quote['chain_days'];
        $record = $handovers->recordReturn($this->root->fresh(), 1000 + 300 * $days + 50, FuelLevel::Full, new Money(0), new Money(0), null, $this->staff);

        expect($record->excess_km)->toBe(50)
            ->and($extension->fresh()->status)->toBe(BookingStatus::Completed);
    });

    it('drops a paid extension from the hire when it is cancelled, and will not cancel the hire under it', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_RECORDED, $this->staff, new Money(840_000), PaymentMethodCode::Cash);
        $cancellations = app(CancellationService::class);

        expect(fn () => $cancellations->cancel($this->root->fresh(), 'Changed plans', new Money(0), 'No refund', $this->staff))
            ->toThrow(BookingException::class, 'paid extensions');

        $cancellations->cancel($extension->fresh(), 'Not needed', new Money(0), 'No refund', $this->staff);

        expect($this->root->fresh()->extended_until)->toBeNull()
            ->and($this->root->fresh()->chainEnd()->equalTo($this->root->return_at))->toBeTrue();
    });

    it('lets an unpaid extension lapse when the hire is cancelled', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);

        app(CancellationService::class)->cancel($this->root->fresh(), 'Changed plans', new Money(0), 'No refund', $this->staff);

        expect($extension->fresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($extension->fresh()->availabilityBlocks()->count())->toBe(0);
    });

    it('shows extensions under their hire on the desk, and waiting ones with the requests', function () {
        $extension = $this->service->request($this->root, nairobi('2026-12-15 11:30'));
        $desk = app(BookingDeskService::class);

        expect($desk->paginate(['bucket' => 'new'])->pluck('id')->all())->toBe([$extension->id]);

        $this->actingAs($this->staff)->post(route('admin.bookings.accept', $extension->reference));
        app(PaymentVerificationService::class)->markPaidByStaff($extension->fresh(), new Money(840_000), PaymentMethodCode::Cash, null, $this->staff);

        expect($desk->paginate(['bucket' => 'upcoming'])->pluck('id')->all())->toBe([$this->root->id]);
    });
});

describe('the renter', function () {
    it('previews, asks for more days and is told it is under review', function () {
        $this->post(route('bookings.extend.preview', $this->token), ['return_at' => '2026-12-15T11:30:00+03:00'])
            ->assertOk()
            ->assertJsonPath('quote.extra_days', 2)
            ->assertJsonPath('quote.amount.minor', 840_000)
            ->assertJsonPath('available', true);

        $this->post(route('bookings.extend', $this->token), ['return_at' => '2026-12-15T11:30:00+03:00', 'note' => 'Flight moved'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $extension = $this->root->extensions()->sole();

        expect($extension->status)->toBe(BookingStatus::Requested)
            ->and($extension->source)->toBe(BookingSource::Website)
            ->and($extension->availabilityBlocks()->count())->toBe(0);

        Notification::assertSentTo(new AnonymousNotifiable, BookingReceived::class, fn (BookingReceived $notification, array $channels, $notifiable) => $notification->booking->is($extension)
            && str_contains($notification->toMail($notifiable)->subject, 'extension request'));

        $this->get(route('bookings.show', $this->token))
            ->assertInertia(fn ($page) => $page
                ->where('booking.can_extend', false)
                ->where('booking.extensions.0.reference', $extension->reference)
                ->where('booking.extensions.0.status', 'requested'));
    });

    it('needs a return after the current one', function () {
        $this->post(route('bookings.extend', $this->token), ['return_at' => '2026-12-12T11:30:00+03:00'])
            ->assertSessionHasErrors('return_at');

        expect($this->root->extensions()->count())->toBe(0);
    });

    it('sees extensions under their booking in the portal, not as bookings of their own', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_RECORDED, $this->staff, new Money(840_000), PaymentMethodCode::Cash);

        $this->withSession([EnsurePortalSession::SESSION_KEY => [
            'type' => 'email', 'value' => 'amina@example.test', 'expires_at' => now()->addHour()->getTimestamp(),
        ]])->get(route('portal.index'))
            ->assertInertia(fn ($page) => $page
                ->has('bookings', 1)
                ->where('bookings.0.reference', $this->root->reference)
                ->where('bookings.0.extensions.0.reference', $extension->reference)
                ->where('bookings.0.chain_end', nairobi('2026-12-15 11:30')->toIso8601String())
                ->where('bookings.0.can_extend', true));
    });

    it('opens an extension to pay for it, and only this booking\'s own', function () {
        $extension = $this->service->create($this->root, nairobi('2026-12-15 11:30'), null, BookingExtensionService::PAY_LINK, $this->staff);
        $other = Booking::factory()->awaitingPayment()->create();

        $response = $this->post(route('bookings.extensions.open', [$this->token, $extension->reference]));
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertInertia(fn ($page) => $page->component('public/booking/status')->where('booking.reference', $extension->reference)->where('booking.extension_of', $this->root->reference));

        $this->post(route('bookings.extensions.open', [$this->token, $other->reference]))->assertNotFound();
    });
});

describe('staff over HTTP', function () {
    it('needs the extend permission', function () {
        $this->actingAs(staffWith([Permission::BookingsView]))
            ->put(route('admin.bookings.extend', $this->root->reference), ['return_at' => '2026-12-15T11:30:00+03:00', 'payment' => 'link'])
            ->assertForbidden();
    });

    it('previews as JSON, then extends with a link for the renter to pay', function () {
        $this->actingAs($this->staff)
            ->postJson(route('admin.bookings.extend.preview', $this->root->reference), ['return_at' => '2026-12-15T11:30:00+03:00'])
            ->assertOk()
            ->assertJsonPath('quote.extra_days', 2)
            ->assertJsonPath('available', true);

        $this->actingAs($this->staff)
            ->put(route('admin.bookings.extend', $this->root->reference), ['return_at' => '2026-12-15T11:30:00+03:00', 'payment' => 'link'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($this->root->extensions()->sole()->status)->toBe(BookingStatus::AwaitingPayment);

        $this->actingAs($this->staff)->get(route('admin.bookings.show', $this->root->reference))
            ->assertInertia(fn ($page) => $page->where('booking.extensions.0.status', 'awaiting_payment')->where('booking.can_extend', false));
    });

    it('records a payment taken at the desk', function () {
        $this->actingAs($this->staff)
            ->put(route('admin.bookings.extend', $this->root->reference), [
                'return_at' => '2026-12-15T11:30:00+03:00', 'payment' => 'paid', 'paid' => '8400', 'method' => 'cash', 'reference' => 'CASH-9',
            ])
            ->assertSessionHasNoErrors();

        $extension = $this->root->extensions()->sole();

        expect($extension->status)->toBe(BookingStatus::Reserved)
            ->and($extension->payments()->sole()->reference)->toBe('CASH-9');
    });

    it('refuses a return before the current one', function () {
        $this->actingAs($this->staff)
            ->put(route('admin.bookings.extend', $this->root->reference), ['return_at' => '2026-12-12T11:30:00+03:00', 'payment' => 'link'])
            ->assertSessionHasErrors('return_at');
    });
});
