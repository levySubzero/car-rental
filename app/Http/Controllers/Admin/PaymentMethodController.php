<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethod\ReorderPaymentMethodsRequest;
use App\Http\Requests\Admin\PaymentMethod\SavePaymentMethodRequest;
use App\Http\Requests\Admin\PaymentMethod\TogglePaymentMethodActiveRequest;
use App\Http\Resources\Admin\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\Cms\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payment methods are a fixed set (D25): staff edit them but cannot add or delete them.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $methods) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        return Inertia::render('admin/payment-methods/index', [
            'methods' => PaymentMethodResource::collection($this->methods->ordered()),
        ]);
    }

    public function edit(PaymentMethod $paymentMethod): Response
    {
        Gate::authorize('view', $paymentMethod);

        return Inertia::render('admin/payment-methods/form', [
            'method' => PaymentMethodResource::make($paymentMethod),
        ]);
    }

    public function update(SavePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->methods->update($paymentMethod, $request->methodData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$paymentMethod->name} saved."]);

        return to_route('admin.payment-methods.edit', $paymentMethod);
    }

    public function active(TogglePaymentMethodActiveRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->methods->setActive($paymentMethod, $request->boolean('value'));

        return back();
    }

    public function reorder(ReorderPaymentMethodsRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->methods->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment method order saved.']);

        return back();
    }
}
