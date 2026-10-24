<?php

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\Policy;
use App\Services\Cms\PageService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('lists what is missing before launch, only to staff who can fix it', function () {
    app(PageService::class)->ensureAll();
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
                'payment-method.mpesa',
                'policy.age-requirements',
                'page.home', 'page.about', 'page.services', 'page.fleet', 'page.rental-policy', 'page.contact', 'page.privacy',
            ]));

    $this->actingAs(staffWith([Permission::PaymentMethodsView]))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('setupChecklist', 1)
            ->where('setupChecklist.0.label', 'M-Pesa'));

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
            ->where('paymentsWaiting', null));

    $this->actingAs(staffWith([Permission::PaymentsView]))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', null)
            ->where('paymentsWaiting', 1));
});
