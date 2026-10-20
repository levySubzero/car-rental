<?php

use App\Enums\Permission;

it('opens the first content tab the user may see', function () {
    $this->actingAs(staffWith([Permission::PoliciesView, Permission::PaymentMethodsView]))
        ->get(route('admin.content.index'))
        ->assertRedirect(route('admin.policies.index'));

    $this->actingAs(superAdmin())
        ->get(route('admin.content.index'))
        ->assertRedirect(route('admin.pages.index'));
});

it('serves the content modules under /admin/content', function () {
    expect(route('admin.pages.index', absolute: false))->toBe('/admin/content/pages')
        ->and(route('admin.policies.publish', 'fuel', absolute: false))->toBe('/admin/content/policies/fuel/publish')
        ->and(route('admin.contact-messages.index', absolute: false))->toBe('/admin/contact-messages');
});
