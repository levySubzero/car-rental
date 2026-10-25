<?php

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\Car;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\Policy;
use App\Services\Cms\PageService;
use App\Settings\GatewaySettings;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('lists what is missing before launch, only to staff who can fix it', function () {
    app(PageService::class)->ensureAll();
    config(['services.daraja.consumer_key' => null, 'services.paystack.secret_key' => null]);
    PaymentMethod::factory()->create(['code' => 'mpesa', 'name' => 'M-Pesa', 'instructions' => 'TODO: paybill']);
    PaymentMethod::factory()->create(['code' => 'cash', 'name' => 'Cash', 'instructions' => 'Pay at the office.']);
    Policy::factory()->withDraft('Age 23 [CONFIRM: 24?]')->create(['title' => 'Age requirements']);
    Policy::factory()->published()->create();

    $this->actingAs(superAdmin())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('setupChecklist', fn ($items) => collect($items)->pluck('key')->values()->all() === [
                'settings.general',
                'settings.home',
                'payments.mpesa',
                'payments.paystack',
                'policy.age-requirements',
                'page.home', 'page.about', 'page.services', 'page.fleet', 'page.rental-policy', 'page.contact', 'page.privacy',
            ]));

    $this->actingAs(staffWith([Permission::PaymentMethodsView]))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('setupChecklist', 2)
            ->where('setupChecklist.0.label', 'M-Pesa online payments'));

    $this->actingAs(staffWith())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('setupChecklist', 0));
});

it('shows the desk day to staff who see bookings, and payments waiting to those who check them', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Africa/Nairobi'));
    $pickup = Booking::factory()->reserved()->create(['pickup_at' => now()->addHours(2), 'return_at' => now()->addDays(2)]);
    PaymentSubmission::factory()->create();

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('today.pickups', 1)
            ->where('today.pickups.0.reference', $pickup->reference)
            ->where('widgets.paymentsWaiting', null));

    $this->actingAs(staffWith([Permission::PaymentsView]))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', null)
            ->where('widgets.paymentsWaiting', 1));
});

it('asks only for the payment gateways, not every method\'s details', function () {
    $daraja = [
        'env' => 'sandbox', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'shortcode' => '174379',
        'passkey' => 'pk', 'type' => 'paybill', 'till_number' => null, 'callback_secret' => 'cb', 'allowed_ips' => [],
    ];
    PaymentMethod::factory()->create(['code' => 'mpesa', 'name' => 'M-Pesa']);
    PaymentMethod::factory()->create(['code' => 'card', 'name' => 'Card']);
    PaymentMethod::factory()->create(['code' => 'bank', 'name' => 'Bank transfer', 'instructions' => 'TODO: bank details']);
    $staff = staffWith([Permission::PaymentMethodsView]);
    $items = fn () => collect($this->actingAs($staff)->get(route('admin.dashboard'))->viewData('page')['props']['setupChecklist'])->keyBy('key');

    config(['services.daraja' => [...$daraja, 'consumer_key' => null], 'services.paystack' => ['public_key' => null, 'secret_key' => null]]);
    expect($items()->keys()->all())->toBe(['payments.mpesa', 'payments.paystack'])
        ->and($items()['payments.mpesa']['detail'])->toBe('Missing: Daraja keys (DARAJA_* on the server), online payments switched on.')
        ->and($items()['payments.paystack']['detail'])->toBe('Missing: Paystack keys (PAYSTACK_* on the server), online payments switched on.');

    config(['services.daraja' => $daraja, 'services.paystack' => ['public_key' => 'pk', 'secret_key' => 'sk']]);
    PaymentMethod::query()->whereIn('code', ['mpesa', 'card'])->update(['online_enabled' => true]);
    expect($items()->keys()->all())->toBe(['payments.mpesa'])
        ->and($items()['payments.mpesa']['detail'])->toContain('mpesa:register-c2b');

    $settings = app(GatewaySettings::class);
    $settings->c2b_registered_at = now()->toIso8601String();
    $settings->c2b_registered_shortcode = '174379';
    $settings->save();
    expect($items()->all())->toBe([]);

    // A till has no paybill registration to do.
    config(['services.daraja' => [...$daraja, 'type' => 'till', 'till_number' => '600100']]);
    expect($items()->all())->toBe([]);
});

it('opens the before-launch popup until it is closed for the session', function () {
    $staff = staffWith([Permission::PaymentMethodsView]);

    $this->actingAs($staff)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('setupReminderDismissed', false));

    $this->actingAs($staff)->post(route('admin.setup-reminder.dismiss'))->assertRedirect();

    $this->actingAs($staff)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('setupReminderDismissed', true));

    // A new session (signing in again) opens it again.
    $this->flushSession();
    $this->actingAs($staff)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('setupReminderDismissed', false));
});

it('sends each widget only to staff with its permission', function (array $permissions, array $visible) {
    $all = ['revenue', 'collected', 'bookings', 'bookingsSeries', 'utilisation', 'topCars', 'paymentsWaiting', 'receiptsOpen', 'openRequests', 'newMessages'];
    $widgets = $this->actingAs(staffWith($permissions))
        ->get(route('admin.dashboard'))
        ->viewData('page')['props']['widgets'];

    expect(array_keys(array_filter($widgets, fn ($widget) => $widget !== null)))->toBe($visible)
        ->and(array_keys($widgets))->toBe($all);
})->with([
    'no permissions' => [[], []],
    'finance' => [[Permission::FinanceView], ['revenue', 'collected']],
    'bookings' => [[Permission::BookingsView], ['bookings', 'bookingsSeries', 'utilisation', 'topCars']],
    'payments' => [[Permission::PaymentsView], ['paymentsWaiting', 'receiptsOpen']],
    'requests' => [[Permission::BookingRequestsView], ['openRequests']],
    'messages' => [[Permission::ContactMessagesView], ['newMessages']],
]);

it('works out the figures and keeps them for five minutes', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-15 09:00', 'Africa/Nairobi'));
    $car = Car::factory()->published()->create(['name' => 'Prado']);
    Car::factory()->published()->create();
    // Ten booked days inside the last 30, and one booking that expired unpaid.
    $booking = Booking::factory()->reserved()->create([
        'car_id' => $car->id,
        'pickup_at' => now()->subDays(12),
        'return_at' => now()->subDays(2),
        'submitted_at' => now()->subDays(14),
    ]);
    Booking::factory()->awaitingPayment()->create(['car_id' => $car->id, 'status' => 'expired', 'submitted_at' => now()->subDay()]);
    PaymentSubmission::factory()->verified(1_000_000)->create(['booking_id' => $booking->id, 'verified_at' => now()->subDays(3)]);

    $props = fn () => $this->actingAs(superAdmin())->get(route('admin.dashboard'))->viewData('page')['props']['widgets'];
    $widgets = $props();

    expect($widgets['revenue'])->toBe(['this_month_minor' => 1_000_000, 'last_month_minor' => 0])
        ->and($widgets['bookings'])->toBe(['this_month' => 1, 'last_month' => 0])
        ->and($widgets['utilisation'])->toBe(['percent' => 17, 'booked_days' => 10.0, 'car_days' => 60])
        ->and($widgets['topCars'])->toBe([['car_id' => $car->id, 'car' => 'Prado', 'days' => 10.0]])
        ->and($widgets['collected'])->toHaveCount(30)
        ->and(collect($widgets['collected'])->sum('value'))->toBe(1_000_000)
        ->and(collect($widgets['bookingsSeries'])->sum('value'))->toBe(1);

    // A new payment shows after the cache expires, not before; queues stay live.
    PaymentSubmission::factory()->verified(500_000)->create(['booking_id' => $booking->id, 'verified_at' => now()]);
    PaymentSubmission::factory()->create(['booking_id' => $booking->id]);
    expect($props()['revenue']['this_month_minor'])->toBe(1_000_000)
        ->and($props()['paymentsWaiting'])->toBe(1);

    $this->travel(6)->minutes();
    expect($props()['revenue']['this_month_minor'])->toBe(1_500_000);
});
