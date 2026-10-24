<?php

use App\Enums\Permission;
use App\Http\Resources\Public\PaymentMethodResource;
use App\Models\PaymentMethod;
use Inertia\Testing\AssertableInertia as Assert;

function methodPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'M-Pesa',
        'instructions' => 'Pay to **Paybill 123456**, account: your booking reference.',
        'requires_transaction_message' => true,
        'requires_proof' => false,
        'is_active' => true,
        'metadata' => ['paybill' => '123456', 'account_format' => ' booking reference ', 'empty' => ''],
    ], $overrides);
}

it('lists methods and flags TODO placeholders', function () {
    PaymentMethod::factory()->create(['code' => 'mpesa', 'instructions' => 'TODO: paybill']);
    PaymentMethod::factory()->create(['code' => 'cash', 'name' => 'Cash']);

    $this->actingAs(staffWith([Permission::PaymentMethodsView]))
        ->get(route('admin.payment-methods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payment-methods/index')
            ->has('methods', 2)
            ->where('methods.0.has_todo', true)
            ->where('methods.1.has_todo', false));
});

it('updates instructions and details, dropping empty details', function () {
    $method = PaymentMethod::factory()->create(['code' => 'mpesa', 'instructions' => 'TODO']);

    $this->actingAs(staffWith([Permission::PaymentMethodsView, Permission::PaymentMethodsUpdate]))
        ->put(route('admin.payment-methods.update', 'mpesa'), methodPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.payment-methods.edit', 'mpesa'));

    expect($method->fresh()->metadata->getArrayCopy())->toBe(['paybill' => '123456', 'account_format' => 'booking reference'])
        ->and($method->fresh()->instructions)->toStartWith('Pay to');
});

it('validates detail names', function () {
    PaymentMethod::factory()->create(['code' => 'bank']);

    $this->actingAs(staffWith([Permission::PaymentMethodsUpdate]))
        ->put(route('admin.payment-methods.update', 'bank'), methodPayload(['metadata' => ['Bank Name' => 'KCB'], 'instructions' => '']))
        ->assertSessionHasErrors(['metadata', 'instructions']);
});

it('reorders and switches methods off', function () {
    $mpesa = PaymentMethod::factory()->create(['code' => 'mpesa']);
    $cash = PaymentMethod::factory()->create(['code' => 'cash']);
    $editor = staffWith([Permission::PaymentMethodsUpdate]);

    $this->actingAs($editor)->put(route('admin.payment-methods.reorder'), ['ids' => [$cash->id, $mpesa->id]])->assertRedirect();
    $this->actingAs($editor)->put(route('admin.payment-methods.active', 'mpesa'), ['value' => false])->assertRedirect();

    expect(PaymentMethod::query()->ordered()->pluck('id')->all())->toBe([$cash->id, $mpesa->id])
        ->and(PaymentMethod::query()->active()->pluck('id')->all())->toBe([$cash->id]);
});

it('keeps details out of the public resource', function () {
    $method = PaymentMethod::factory()->create(['metadata' => ['paybill' => '123456']]);

    expect(PaymentMethodResource::make($method)->resolve())
        ->not->toHaveKey('metadata')
        ->toHaveKey('instructions_html');
});

it('has no create or delete routes, and needs permission to update', function () {
    PaymentMethod::factory()->create(['code' => 'card']);

    expect(Route::has('admin.payment-methods.store'))->toBeFalse()
        ->and(Route::has('admin.payment-methods.destroy'))->toBeFalse();

    $this->actingAs(staffWith([Permission::PaymentMethodsView]))
        ->put(route('admin.payment-methods.update', 'card'), methodPayload())
        ->assertForbidden();
});

it('switches M-Pesa and card online, and ignores the switch for other methods', function () {
    $mpesa = PaymentMethod::factory()->create(['code' => 'mpesa']);
    $bank = PaymentMethod::factory()->create(['code' => 'bank', 'name' => 'Bank transfer']);
    $editor = staffWith([Permission::PaymentMethodsView, Permission::PaymentMethodsUpdate]);

    $this->actingAs($editor)->put(route('admin.payment-methods.update', 'mpesa'), methodPayload(['online_enabled' => true]))->assertSessionHasNoErrors();
    $this->actingAs($editor)->put(route('admin.payment-methods.update', 'bank'), methodPayload(['name' => 'Bank transfer', 'online_enabled' => true]))->assertSessionHasNoErrors();

    expect($mpesa->fresh()->online_enabled)->toBeTrue()
        ->and($bank->fresh()->online_enabled)->toBeFalse();

    // Leaving the field out keeps the switch as it was.
    $this->actingAs($editor)->put(route('admin.payment-methods.update', 'mpesa'), methodPayload())->assertSessionHasNoErrors();
    expect($mpesa->fresh()->online_enabled)->toBeTrue();
});

it('tells staff whether the gateway keys are set', function () {
    PaymentMethod::factory()->create(['code' => 'card', 'name' => 'Card', 'online_enabled' => true]);
    $viewer = staffWith([Permission::PaymentMethodsView]);
    config(['services.paystack' => ['public_key' => null, 'secret_key' => null]]);

    $this->actingAs($viewer)->get(route('admin.payment-methods.edit', 'card'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('method.supports_online', true)
            ->where('method.online_enabled', true)
            ->where('method.gateway_configured', false));

    config(['services.paystack' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1']]);

    $this->actingAs($viewer)->get(route('admin.payment-methods.edit', 'card'))
        ->assertInertia(fn (Assert $page) => $page->where('method.gateway_configured', true));
});
