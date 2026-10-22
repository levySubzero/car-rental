<?php

namespace App\Http\Controllers\Public;

use App\Enums\Transmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Fleet\FleetRequest;
use App\Http\Resources\Public\CarCardResource;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Services\Cms\PageContent;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fleet (M7.9) and its filtered views (M7.10): /fleet takes the filters from the query
 * string; /car-brands/{brand} and /car-types/{type} fix one of them (TECHNICAL_PLAN §14).
 * A hidden car type has no page and cannot be filtered on, but its cars stay in the fleet.
 */
class FleetController extends Controller
{
    public function index(FleetRequest $request): Response
    {
        return $this->render($request, null);
    }

    public function brand(FleetRequest $request, Brand $brand): Response
    {
        abort_unless($brand->is_active, 404);

        return $this->render($request, ['kind' => 'brand', 'slug' => $brand->slug, 'name' => $brand->name, 'description' => null]);
    }

    public function carType(FleetRequest $request, CarType $carType): Response
    {
        abort_unless($carType->is_active, 404);

        return $this->render($request, ['kind' => 'type', 'slug' => $carType->slug, 'name' => $carType->name, 'description' => $carType->description]);
    }

    /**
     * @param  array{kind: 'brand'|'type', slug: string, name: string, description: string|null}|null  $preset
     */
    private function render(FleetRequest $request, ?array $preset): Response
    {
        $filters = $request->filters();

        // An unknown, inactive or hidden brand or type in the query is ignored, not an empty fleet.
        if ($filters['brand'] !== null && ! Brand::query()->active()->where('slug', $filters['brand'])->exists()) {
            $filters['brand'] = null;
        }

        if ($filters['type'] !== null && ! CarType::query()->active()->where('slug', $filters['type'])->exists()) {
            $filters['type'] = null;
        }

        if ($preset !== null) {
            $filters[$preset['kind']] = $preset['slug'];
        }

        $cars = $this->query($filters)
            ->with(['brand.logo', 'carType', 'cover'])
            ->paginate(FleetRequest::PER_PAGE)
            ->withQueryString();

        $page = app(PageContent::class)->for('fleet');

        return Inertia::render('public/fleet', [
            'heading' => $preset['name'] ?? PageContent::text($page, 'heading', 'Our fleet'),
            'intro' => $preset !== null ? $preset['description'] : PageContent::text($page, 'intro'),
            // Brand and car type pages share the fleet's header image.
            'header_image' => $page['header_image'] ?? null,
            'preset' => $preset === null ? null : ['kind' => $preset['kind'], 'slug' => $preset['slug']],
            'filters' => $filters,
            'options' => $this->options(),
            'cars' => CarCardResource::collection($cars),
        ]);
    }

    /**
     * @param  array{brand: string|null, type: string|null, transmission: string|null, seats: int|null, sort: string}  $filters
     * @return Builder<Car>
     */
    private function query(array $filters): Builder
    {
        $query = Car::query()->published();

        if ($filters['brand'] !== null) {
            $query->whereHas('brand', fn (Builder $brand) => $brand->where('slug', $filters['brand'])->where('is_active', true));
        }

        if ($filters['type'] !== null) {
            $query->whereHas('carType', fn (Builder $type) => $type->where('slug', $filters['type'])->where('is_active', true));
        }

        if ($filters['transmission'] !== null) {
            $query->where('transmission', $filters['transmission']);
        }

        if ($filters['seats'] !== null) {
            $query->where('seats', '>=', $filters['seats']);
        }

        return match ($filters['sort']) {
            'price_asc' => $query->orderBy('daily_rate_minor')->orderBy('id'),
            'price_desc' => $query->orderByDesc('daily_rate_minor')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->ordered()->orderBy('id'),
        };
    }

    /**
     * Filter choices: only brands and types that have published cars, so no choice is empty.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'brands' => Brand::query()->active()->hasPublishedCars()->orderBy('name')->get(['name', 'slug']),
            'types' => CarType::query()->active()->hasPublishedCars()->ordered()->get(['name', 'slug']),
            'transmissions' => array_map(fn (Transmission $t) => ['value' => $t->value, 'label' => $t->label()], Transmission::cases()),
            'seats' => FleetRequest::SEATS,
        ];
    }
}
