<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\Car;
use App\Models\PaymentSubmission;
use App\Services\Booking\BookingTokenService;
use App\Services\Booking\CancellationService;
use App\Services\Booking\KycPurgeService;
use App\Support\Money;
use Database\Seeders\PricingSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    Notification::fake();
    $this->car = Car::factory()->published()->create(['daily_rate_minor' => 1_500_000, 'car_value_minor' => 800_000_000]);
    $this->staff = staffWith([Permission::BookingsView, Permission::BookingsCreate, Permission::BookingsCancel, Permission::BookingsResendVoucher]);
});

function offlinePayload(array $overrides = []): array
{
    return [
        'car_id' => test()->car->id,
        'first_name' => 'Juma',
        'surname' => 'Hassan',
        'phone' => '+254 700 000 002',
        'pickup_at' => now()->addDays(5)->toIso8601String(),
        'return_at' => now()->addDays(8)->toIso8601String(),
        'pickup_mode' => 'office',
        'return_mode' => 'office',
        ...$overrides,
    ];
}

it('needs the permission', function () {
    $this->actingAs(staffWith([Permission::BookingsView]))->get(route('admin.bookings.create'))->assertForbidden();
    $this->actingAs(staffWith([Permission::BookingsView]))->post(route('admin.bookings.store'), offlinePayload())->assertForbidden();
});

it('shows the form', function () {
    $this->actingAs($this->staff)->get(route('admin.bookings.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/bookings/create')->has('cars', 1)->has('modes', 2));
});

it('confirms the booking, blocks the car, and goes on to the voucher', function () {
    $response = $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload([
        'email' => 'juma@example.test', 'total' => '30000', 'deposit' => '10000', 'paid' => '5000', 'payment_method' => 'cash',
    ]));

    $booking = Booking::sole();
    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.bookings.voucher.show', $booking->reference));

    expect($booking->source)->toBe(BookingSource::Admin)
        ->and($booking->status)->toBe(BookingStatus::Reserved)
        ->and($booking->total_minor)->toBe(3_000_000)
        ->and($booking->security_deposit_minor)->toBe(1_000_000)
        ->and($booking->reservation_paid_minor)->toBe(500_000)
        ->and($booking->balance_minor)->toBe(2_500_000)
        ->and($booking->availabilityBlocks()->sole()->type->value)->toBe('booked')
        ->and($booking->policy_bundle_id)->toBeNull()
        ->and($booking->voucherUnsent())->toBeTrue();

    $this->actingAs($this->staff)->get(route('admin.bookings.voucher.show', $booking->reference))->assertOk();
    $this->actingAs($this->staff)->get(route('admin.bookings.index', ['bucket' => 'upcoming']))
        ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 1));
});

it('uses the car\'s normal rate when no price is given', function () {
    $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload())->assertSessionHasNoErrors();

    $booking = Booking::sole();

    expect($booking->total_minor)->toBe((int) $booking->quote['total']['minor'])
        ->and($booking->quote)->not->toHaveKey('staff_adjustment')
        ->and($booking->reservation_paid_minor)->toBe(0)
        ->and($booking->balance_minor)->toBe($booking->total_minor)
        ->and(PaymentSubmission::count())->toBe(0);
});

it('refuses dates the car is already taken for', function () {
    $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload())->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload());

    expect(Booking::count())->toBe(1);
});

it('needs a way to reach the client', function () {
    $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload(['phone' => null]))
        ->assertSessionHasErrors(['email', 'phone']);
});

it('counts money taken in Finance', function () {
    $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload(['paid' => '5000', 'payment_method' => 'cash', 'payment_reference' => 'R-9']))->assertSessionHasNoErrors();

    $payment = PaymentSubmission::sole();

    expect($payment->status->value)->toBe('verified')
        ->and($payment->amount_minor)->toBe(500_000)
        ->and($payment->reference)->toBe('R-9')
        ->and($payment->verified_at)->not->toBeNull();
});

it('asks for a payment method when money was taken', function () {
    $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload(['paid' => '5000']))
        ->assertSessionHasErrors('payment_method');
});

describe('a client with no ID, licence or email on file', function () {
    beforeEach(function () {
        $this->actingAs($this->staff)->post(route('admin.bookings.store'), offlinePayload())->assertSessionHasNoErrors();
        $this->booking = Booking::sole();
    });

    it('needs no voucher sending, and is not flagged as waiting', function () {
        expect($this->booking->voucherUnsent())->toBeFalse();
    });

    it('shows the booking to staff', function () {
        $this->actingAs($this->staff)->get(route('admin.bookings.show', $this->booking->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('booking.driver.name', 'Juma Hassan')->where('booking.driver.dob', null));
    });

    it('opens from a renter link', function () {
        $token = app(BookingTokenService::class)->rotate($this->booking);

        $this->get(route('bookings.show', $token))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/booking/status')->where('booking.status', 'reserved'));
    });

    it('can be handed over', function () {
        $this->actingAs(staffWith([Permission::BookingsView, Permission::BookingsRecordHandover]))
            ->post(route('admin.bookings.handover', $this->booking->reference), [
                'odometer_km' => 12000, 'fuel_level' => 'full', 'balance_received' => (string) ($this->booking->balance_minor / 100), 'deposit_received' => '0',
            ])
            ->assertSessionHasNoErrors();

        expect($this->booking->fresh()->status)->toBe(BookingStatus::Active);
    });

    it('can be purged of KYC data', function () {
        app(KycPurgeService::class)->purgeBooking($this->booking);

        expect($this->booking->fresh()->kyc_purged_at)->not->toBeNull()
            ->and($this->booking->driver->fresh()->fullName())->toBe('Juma Hassan');
    });

    it('can be cancelled', function () {
        app(CancellationService::class)->cancel($this->booking, 'Client changed plans.', new Money(0), null, $this->staff);

        expect($this->booking->fresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($this->booking->availabilityBlocks()->count())->toBe(0);
    });
});
