<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryZone\ReorderDeliveryZonesRequest;
use App\Http\Requests\Admin\DeliveryZone\SaveDeliveryZoneRequest;
use App\Http\Resources\Admin\DeliveryZoneResource;
use App\Models\DeliveryZone;
use App\Services\Pricing\DeliveryZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryZoneController extends Controller
{
    public function __construct(private readonly DeliveryZoneService $zones) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', DeliveryZone::class);

        return Inertia::render('admin/delivery-zones/index', [
            'zones' => DeliveryZoneResource::collection($this->zones->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', DeliveryZone::class);

        return Inertia::render('admin/delivery-zones/form', ['zone' => null]);
    }

    public function store(SaveDeliveryZoneRequest $request): RedirectResponse
    {
        $zone = $this->zones->create($request->zoneData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Delivery zone {$zone->name} created."]);

        return to_route('admin.delivery-zones.index');
    }

    public function edit(DeliveryZone $deliveryZone): Response
    {
        Gate::authorize('view', $deliveryZone);

        return Inertia::render('admin/delivery-zones/form', ['zone' => DeliveryZoneResource::make($deliveryZone)]);
    }

    public function update(SaveDeliveryZoneRequest $request, DeliveryZone $deliveryZone): RedirectResponse
    {
        $this->zones->update($deliveryZone, $request->zoneData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery zone saved.']);

        return to_route('admin.delivery-zones.index');
    }

    public function reorder(ReorderDeliveryZonesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->zones->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order saved.']);

        return back();
    }

    public function destroy(DeliveryZone $deliveryZone): RedirectResponse
    {
        Gate::authorize('delete', $deliveryZone);

        $this->zones->delete($deliveryZone);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery zone deleted.']);

        return to_route('admin.delivery-zones.index');
    }
}
