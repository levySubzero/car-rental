<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepositBand\SaveDepositBandRequest;
use App\Http\Resources\Admin\DepositBandResource;
use App\Models\DepositBand;
use App\Services\Pricing\DepositBandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepositBandController extends Controller
{
    public function __construct(private readonly DepositBandService $bands) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', DepositBand::class);

        return Inertia::render('admin/deposit-bands/index', [
            'bands' => DepositBandResource::collection($this->bands->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', DepositBand::class);

        return Inertia::render('admin/deposit-bands/form', ['band' => null]);
    }

    public function store(SaveDepositBandRequest $request): RedirectResponse
    {
        $this->bands->create($request->bandData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Deposit band created.']);

        return to_route('admin.deposit-bands.index');
    }

    public function edit(DepositBand $depositBand): Response
    {
        Gate::authorize('view', $depositBand);

        return Inertia::render('admin/deposit-bands/form', ['band' => DepositBandResource::make($depositBand)]);
    }

    public function update(SaveDepositBandRequest $request, DepositBand $depositBand): RedirectResponse
    {
        $this->bands->update($depositBand, $request->bandData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Deposit band saved.']);

        return to_route('admin.deposit-bands.index');
    }

    public function destroy(DepositBand $depositBand): RedirectResponse
    {
        Gate::authorize('delete', $depositBand);

        $this->bands->delete($depositBand);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Deposit band deleted.']);

        return to_route('admin.deposit-bands.index');
    }
}
