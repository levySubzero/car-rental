<?php

use App\Enums\AvailabilityBlockType;
use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\Car;
use App\Models\CarType;
use App\Notifications\Booking\BookingAccepted;
use App\Notifications\Booking\BookingCancelled;
use App\Notifications\StaffAlert;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingDeskService;
use App\Services\Booking\DeliveryAdjustmentService;
use App\Services\Notifications\BookingNotifier;
use App\Settings\NotificationSettings;
use App\Support\Money;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function websiteRequest(array $attributes = []): Booking
{
    $booking = Booking::factory()->requested()->create([
        'pickup_at' => now()->addDays(10),
        'return_at' => now()->addDays(13),
        'expires_at' => $attributes['pickup_at'] ?? now()->addDays(10),
        ...$attributes,
    ]);
    BookingDriver::factory()->for($booking)->create(['email' => 'amina@example.test']);

    return $booking;
}

function accepter()
{
    return staffWith([Permission::BookingsView, Permission::BookingsAccept]);
}

beforeEach(fn () => Notification::fake());

describe('accepting', function () {
    it('holds the car, opens the payment window, and emails the renter a new link', function () {
        $booking = websiteRequest();
        $oldHash = $booking->access_token_hash;

        $this->actingAs(accepter())->post(route('admin.bookings.accept', $booking->reference))->assertSessionHasNoErrors();

        $booking->refresh();
        $hold = $booking->availabilityBlocks()->sole();

        expect($booking->status)->toBe(BookingStatus::AwaitingPayment)
            ->and($hold->type)->toBe(AvailabilityBlockType::Hold)
            ->and($hold->expires_at->toIso8601String())->toBe(now()->addMinutes(1440)->toIso8601String())
            ->and($booking->expires_at->toIso8601String())->toBe($hold->expires_at->toIso8601String())
            ->and($booking->access_token_hash)->not->toBe($oldHash);

        Notification::assertSentOnDemand(BookingAccepted::class, fn (BookingAccepted $mail) => $mail->token !== null
            && $mail->toMail(new AnonymousNotifiable)->actionUrl === route('bookings.show', $mail->token));
    });

    it('caps the payment window at pick-up', function () {
        $booking = websiteRequest(['pickup_at' => now()->addHours(5), 'return_at' => now()->addDays(2)]);

        $this->actingAs(accepter())->post(route('admin.bookings.accept', $booking->reference));

        expect($booking->fresh()->expires_at->toIso8601String())->toBe($booking->pickup_at->toIso8601String());
    });

    it('lets only one of two overlapping requests win, and leaves the other open', function () {
        $car = Car::factory()->published()->create();
        $first = websiteRequest(['car_id' => $car->id]);
        $second = websiteRequest(['car_id' => $car->id]);
        $staff = accepter();

        $this->actingAs($staff)->post(route('admin.bookings.accept', $first->reference))->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.bookings.accept', $second->reference));

        expect($first->fresh()->status)->toBe(BookingStatus::AwaitingPayment)
            ->and($second->fresh()->status)->toBe(BookingStatus::Requested)
            ->and(app(AvailabilityService::class)->isTakenFromRequest($second->fresh()))->toBeTrue()
            ->and(AvailabilityBlock::count())->toBe(1);
    });

    it('needs the accept permission', function () {
        $booking = websiteRequest();

        $this->actingAs(staffWith([Permission::BookingsView]))
            ->post(route('admin.bookings.accept', $booking->reference))
            ->assertForbidden();

        expect($booking->fresh()->status)->toBe(BookingStatus::Requested);
    });

    it('applies a price and deposit set by staff to the columns and the snapshot', function () {
        $booking = websiteRequest();

        $this->actingAs(accepter())->post(route('admin.bookings.accept', $booking->reference), ['total' => '40000', 'deposit' => '25000'])->assertSessionHasNoErrors();

        $booking->refresh();
        $line = collect($booking->quote['lines'])->firstWhere('code', 'staff_adjustment');

        expect($booking->total_minor)->toBe(4_000_000)
            // 25% of the new total, and the rest at handover.
            ->and($booking->reservation_minor)->toBe(1_000_000)
            ->and($booking->balance_minor)->toBe(3_000_000)
            ->and($booking->security_deposit_minor)->toBe(2_500_000)
            ->and($booking->quote['total']['minor'])->toBe(4_000_000)
            ->and($booking->quote['reservation']['minor'])->toBe(1_000_000)
            ->and($booking->quote['deposit']['minor'])->toBe(2_500_000)
            ->and($line['amount']['minor'])->toBe(-2_635_000)
            ->and($booking->quote['staff_adjustment']['total_minor'])->toBe(6_635_000)
            // Worked out at the snapshot's own display rate: 40,000 ÷ (129 − 3).
            ->and($booking->quote['display']['USD']['total']['minor'])->toBe(31746);
    });

    it('keeps a staff price when the delivery fee changes afterwards', function () {
        $booking = websiteRequest(['pickup_mode' => 'address', 'delivery_address' => 'Westlands']);
        $staff = accepter();

        $this->actingAs($staff)->post(route('admin.bookings.accept', $booking->reference), ['total' => '40000']);
        app(DeliveryAdjustmentService::class)->setFee($booking->fresh(), new Money(200_000), $staff);

        expect($booking->fresh()->total_minor)->toBe(4_200_000)
            ->and($booking->fresh()->balance_minor)->toBe(3_200_000);
    });

    it('changes nothing when the figures are left as they are', function () {
        $booking = websiteRequest();

        $this->actingAs(accepter())->post(route('admin.bookings.accept', $booking->reference), ['total' => '66350.00', 'deposit' => '160000.00']);

        expect($booking->fresh()->quote)->not->toHaveKey('staff_adjustment');
    });

    it('warns staff when the acceptance email is off', function () {
        $settings = app(NotificationSettings::class);
        $settings->channels = [...$settings->channels, 'booking_accepted' => ['mail' => false, 'whatsapp' => false]];
        $settings->save();
        $booking = websiteRequest();
        $oldHash = $booking->access_token_hash;

        $this->actingAs(accepter())->post(route('admin.bookings.accept', $booking->reference));

        expect($booking->fresh()->access_token_hash)->toBe($oldHash);
    });
});

it('refuses payment until a request is accepted', function () {
    $booking = Booking::factory()->requested()->withToken($token = str_repeat('a', 64))->create([
        'pickup_at' => now()->addDays(10),
        'return_at' => now()->addDays(13),
    ]);

    $this->post(route('bookings.payment', $token), ['payment_method_code' => 'cash']);

    expect(BookingStatus::Requested->acceptsPayment())->toBeFalse()
        ->and($booking->fresh()->status)->toBe(BookingStatus::Requested)
        ->and($booking->payments()->count())->toBe(0);
});

describe('declining', function () {
    it('emails the reason and invites the renter to pick another car of the type', function () {
        $type = CarType::factory()->create(['slug' => 'suvs']);
        $booking = websiteRequest(['car_id' => Car::factory()->published()->create(['car_type_id' => $type->id])->id]);

        $this->actingAs(staffWith([Permission::BookingsView, Permission::BookingsCancel]))
            ->post(route('admin.bookings.cancel', $booking->reference), ['reason' => 'That car is in service.', 'refund' => '0'])
            ->assertSessionHasNoErrors();

        expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);

        Notification::assertSentOnDemand(BookingCancelled::class, function (BookingCancelled $mail) {
            $message = $mail->toMail(new AnonymousNotifiable);

            return collect($message->introLines)->contains('**Reason:** That car is in service.')
                && $message->actionUrl === route('fleet', ['type' => 'suvs']);
        });
    });

    it('tells a paid renter the reason and refund, with no prompt to choose again', function () {
        $booking = Booking::factory()->reserved()->create(['pickup_at' => now()->addDays(10), 'return_at' => now()->addDays(13), 'cancellation_tiers' => []]);
        BookingDriver::factory()->for($booking)->create();

        $this->actingAs(staffWith([Permission::BookingsView, Permission::BookingsCancel]))
            ->post(route('admin.bookings.cancel', $booking->reference), ['reason' => 'Car damaged.', 'refund' => '16588', 'refund_override_reason' => 'Full refund, our fault.'])
            ->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(BookingCancelled::class, function (BookingCancelled $mail) {
            $message = $mail->toMail(new AnonymousNotifiable);

            return $message->actionUrl === null
                && collect($message->introLines)->contains('**Reason:** Car damaged.')
                && collect($message->introLines)->contains(fn ($line) => str_starts_with($line, '**Refund:** KES 16,588'));
        });
    });
});

describe('expiry', function () {
    it('closes an unanswered request at pick-up and emails the same invitation', function () {
        $booking = websiteRequest();

        $this->travelTo($booking->pickup_at->addMinute());
        $this->artisan('bookings:expire')->assertSuccessful();

        expect($booking->fresh()->status)->toBe(BookingStatus::Expired);
        Notification::assertSentOnDemand(BookingCancelled::class, fn (BookingCancelled $mail) => $mail->toMail(new AnonymousNotifiable)->actionUrl !== null
            && str_contains($mail->reason, 'review'));
    });

    it('stays silent for a draft nobody finished', function () {
        $draft = Booking::factory()->create(['updated_at' => now()->subDays(3)]);

        $this->artisan('bookings:expire')->assertSuccessful();

        expect($draft->fresh()->status)->toBe(BookingStatus::Expired);
        Notification::assertNothingSent();
    });
});

describe('the two screens', function () {
    beforeEach(function () {
        websiteRequest();
        Booking::factory()->awaitingPayment()->create();
        Booking::factory()->requested()->create(['status' => BookingStatus::Cancelled]);
        Booking::factory()->reserved()->create();
        Booking::factory()->reserved()->create(['status' => BookingStatus::Cancelled]);
        Booking::factory()->create();
    });

    it('keeps anything unpaid off Bookings', function () {
        expect(app(BookingDeskService::class)->counts())->toBe([
            'new' => 1, 'unpaid' => 1, 'declined' => 1,
            'upcoming' => 1, 'on_hire' => 0, 'closed' => 1, 'all' => 2,
        ]);
    });

    it('opens each screen on its own first tab, with only its own tabs', function () {
        $staff = staffWith([Permission::BookingsView]);

        $this->actingAs($staff)->get(route('admin.bookings.requests'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/requests')
                ->where('filters.bucket', 'new')
                ->where('buckets', fn ($buckets) => collect($buckets)->pluck('value')->all() === ['new', 'unpaid', 'declined'])
                ->has('bookings.data', 1));

        $this->actingAs($staff)->get(route('admin.bookings.index', ['bucket' => 'new']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/index')
                ->where('filters.bucket', 'upcoming')
                ->where('buckets', fn ($buckets) => collect($buckets)->pluck('value')->all() === ['upcoming', 'on_hire', 'closed', 'all']));
    });
});

describe('in-app alerts', function () {
    it('sends each update to staff with the permission and nobody else', function () {
        $viewer = staffWith([Permission::BookingsView]);
        $accepter = accepter();
        $outsider = staffWith([Permission::CarsView]);
        $booking = websiteRequest();

        $this->actingAs($accepter)->post(route('admin.bookings.accept', $booking->reference));

        foreach ([$viewer, $accepter] as $staff) {
            Notification::assertSentTo($staff, StaffAlert::class, fn (StaffAlert $alert) => $alert->title === "Request accepted: {$booking->reference}"
                && $alert->url === route('admin.bookings.show', $booking->reference));
        }

        Notification::assertNotSentTo($outsider, StaffAlert::class);
    });

    it('sends a new request only to staff who can accept it', function () {
        $viewer = staffWith([Permission::BookingsView]);
        $accepter = accepter();
        $booking = websiteRequest();

        app(BookingNotifier::class)->submitted($booking, str_repeat('a', 64));

        Notification::assertSentTo($accepter, StaffAlert::class, fn (StaffAlert $alert) => $alert->title === "New request {$booking->reference}");
        Notification::assertNotSentTo($viewer, StaffAlert::class);
    });
});
