<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancellationTier\SaveCancellationTierRequest;
use App\Http\Resources\Admin\CancellationTierResource;
use App\Models\CancellationTier;
use App\Services\Pricing\CancellationTierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CancellationTierController extends Controller
{
    public function __construct(private readonly CancellationTierService $tiers) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', CancellationTier::class);

        return Inertia::render('admin/cancellation-tiers/index', [
            'tiers' => CancellationTierResource::collection($this->tiers->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', CancellationTier::class);

        return Inertia::render('admin/cancellation-tiers/form', ['tier' => null]);
    }

    public function store(SaveCancellationTierRequest $request): RedirectResponse
    {
        $this->tiers->create($request->tierData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cancellation tier created.']);

        return to_route('admin.cancellation-tiers.index');
    }

    public function edit(CancellationTier $cancellationTier): Response
    {
        Gate::authorize('view', $cancellationTier);

        return Inertia::render('admin/cancellation-tiers/form', ['tier' => CancellationTierResource::make($cancellationTier)]);
    }

    public function update(SaveCancellationTierRequest $request, CancellationTier $cancellationTier): RedirectResponse
    {
        $this->tiers->update($cancellationTier, $request->tierData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cancellation tier saved.']);

        return to_route('admin.cancellation-tiers.index');
    }

    public function destroy(CancellationTier $cancellationTier): RedirectResponse
    {
        Gate::authorize('delete', $cancellationTier);

        $this->tiers->delete($cancellationTier);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cancellation tier deleted.']);

        return to_route('admin.cancellation-tiers.index');
    }
}
