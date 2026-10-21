<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Charge\ReorderChargesRequest;
use App\Http\Requests\Admin\Charge\SaveChargeRequest;
use App\Http\Resources\Admin\ChargeResource;
use App\Models\Charge;
use App\Services\Pricing\ChargeService;
use App\Services\Pricing\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChargeController extends Controller
{
    public function __construct(private readonly ChargeService $charges) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Charge::class);

        return Inertia::render('admin/charges/index', [
            'charges' => ChargeResource::collection($this->charges->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Charge::class);

        return Inertia::render('admin/charges/form', ['charge' => null, ...$this->formOptions()]);
    }

    public function store(SaveChargeRequest $request): RedirectResponse
    {
        $charge = $this->charges->create($request->chargeData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Charge {$charge->name} created."]);

        return to_route('admin.charges.index');
    }

    public function edit(Charge $charge): Response
    {
        Gate::authorize('view', $charge);

        return Inertia::render('admin/charges/form', ['charge' => ChargeResource::make($charge), ...$this->formOptions()]);
    }

    public function update(SaveChargeRequest $request, Charge $charge): RedirectResponse
    {
        $this->charges->update($charge, $request->chargeData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Charge saved.']);

        return to_route('admin.charges.index');
    }

    public function reorder(ReorderChargesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->charges->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order saved.']);

        return back();
    }

    public function destroy(Charge $charge): RedirectResponse
    {
        Gate::authorize('delete', $charge);

        $this->charges->delete($charge);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Charge deleted.']);

        return to_route('admin.charges.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'basisOptions' => array_map(fn (ChargeBasis $basis) => ['value' => $basis->value, 'label' => $basis->label()], ChargeBasis::cases()),
            'payableAtOptions' => array_map(fn (ChargePayableAt $at) => ['value' => $at->value, 'label' => $at->label()], ChargePayableAt::cases()),
            'appliesOptions' => array_map(fn (ChargeApplies $applies) => ['value' => $applies->value, 'label' => $applies->label()], ChargeApplies::cases()),
            'currencyOptions' => app(CurrencyService::class)->options(),
        ];
    }
}
