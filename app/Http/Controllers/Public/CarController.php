<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\CarCardResource;
use App\Http\Resources\Public\CarDetailResource;
use App\Models\Car;
use App\Settings\PricingSettings;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A car's page (M7.11, PRD F-3–F-5). Drafts and deleted cars are not found.
 */
class CarController extends Controller
{
    private const RELATED = 4;

    public function show(Car $car, PricingSettings $pricing): Response
    {
        abort_unless($car->is_published, 404);

        $car->load(['brand.logo', 'carType', 'cover', 'gallery']);

        return Inertia::render('public/car', [
            'car' => CarDetailResource::make($car),
            'related' => CarCardResource::collection($this->related($car)),
            // "Pay 25% to reserve": basis points as a whole or decimal percentage.
            'reservation_percent' => $pricing->reservation_percent_bp / 100,
        ]);
    }

    /**
     * Other published cars of the same type first, topped up with any others.
     *
     * @return Collection<int, Car>
     */
    private function related(Car $car): Collection
    {
        $base = fn () => Car::query()->published()->whereKeyNot($car->id)->with(['brand.logo', 'carType', 'cover']);

        $sameType = $base()->where('car_type_id', $car->car_type_id)->orderByDesc('is_featured')->ordered()->limit(self::RELATED)->get();

        if ($sameType->count() >= self::RELATED) {
            return $sameType;
        }

        $others = $base()->whereKeyNot($sameType->modelKeys())->orderByDesc('is_featured')->ordered()
            ->limit(self::RELATED - $sameType->count())->get();

        return $sameType->concat($others)->values();
    }
}
