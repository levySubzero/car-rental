<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Availability\IndexAvailabilityRequest;
use App\Models\AvailabilityBlock;
use App\Models\Car;
use App\Services\Booking\AvailabilityService;
use App\Settings\PricingSettings;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The availability calendar (M11.9): every car against the days ahead, with holds, firm
 * bookings and staff blocks read from the one blocks table (D35).
 */
class AvailabilityController extends Controller
{
    public function index(IndexAvailabilityRequest $request, AvailabilityService $availability, PricingSettings $pricing): Response
    {
        $zone = (string) config('baines.timezone');
        $start = CarbonImmutable::parse((string) ($request->validated('start') ?? CarbonImmutable::now($zone)->toDateString()), $zone)->startOfDay();
        $days = (int) ($request->validated('days') ?? 14);
        $range = new DateRange($start->utc(), $start->addDays($days)->utc());

        $car = $request->validated('car') === null ? null : Car::query()->find((int) $request->validated('car'));

        return Inertia::render('admin/availability/index', [
            'filters' => ['start' => $start->toDateString(), 'days' => $days, 'car' => $car === null ? null : (string) $car->id],
            'start' => $start->toIso8601String(),
            'days' => $days,
            'turnaroundMinutes' => $pricing->turnaround_minutes,
            'cars' => Car::query()
                ->when($car !== null, fn ($query) => $query->whereKey($car?->id))
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_published'])
                ->map(fn (Car $item) => ['id' => $item->id, 'name' => $item->name, 'slug' => $item->slug, 'published' => (bool) $item->is_published]),
            'carOptions' => Car::query()->orderBy('name')->get(['id', 'name'])->map(fn (Car $item) => ['value' => (string) $item->id, 'label' => $item->name]),
            'blocks' => $availability->calendar($range, $car)->map(fn (AvailabilityBlock $block) => [
                'id' => $block->id,
                'car_id' => $block->car_id,
                'type' => $block->type->value,
                'starts_at' => $block->starts_at->toIso8601String(),
                'ends_at' => $block->ends_at->toIso8601String(),
                'reason' => $block->reason?->label(),
                'notes' => $block->notes,
                'booking' => $block->booking === null ? null : [
                    'reference' => $block->booking->reference,
                    'status_label' => $block->booking->status->label(),
                ],
            ]),
        ]);
    }
}
