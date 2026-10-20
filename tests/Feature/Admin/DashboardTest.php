<?php

use App\Enums\Permission;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Services\Cms\PageService;
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
