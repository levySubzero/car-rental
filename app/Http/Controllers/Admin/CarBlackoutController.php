<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Car\SaveCarBlackoutRequest;
use App\Models\Car;
use App\Models\CarBlackout;
use App\Services\Catalog\CarBlackoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Maintenance and private-use periods, managed from the car's edit page.
 */
class CarBlackoutController extends Controller
{
    public function __construct(private readonly CarBlackoutService $blackouts) {}

    public function store(SaveCarBlackoutRequest $request, Car $car): RedirectResponse
    {
        /** @var array{starts_at: string, ends_at: string, reason: string, notes?: string|null} $data */
        $data = $request->validated();
        $this->blackouts->create($car, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blackout added.']);

        return back();
    }

    public function update(SaveCarBlackoutRequest $request, Car $car, CarBlackout $blackout): RedirectResponse
    {
        /** @var array{starts_at: string, ends_at: string, reason: string, notes?: string|null} $data */
        $data = $request->validated();
        $this->blackouts->update($blackout, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blackout saved.']);

        return back();
    }

    public function destroy(Car $car, CarBlackout $blackout): RedirectResponse
    {
        Gate::authorize('manageAvailability', $car);

        $this->blackouts->delete($blackout);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blackout removed.']);

        return back();
    }
}
