<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlackoutReason;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Car\IndexCarRequest;
use App\Http\Requests\Admin\Car\SaveCarRequest;
use App\Http\Requests\Admin\Car\ToggleCarFlagRequest;
use App\Http\Resources\Admin\CarResource;
use App\Models\Car;
use App\Services\Catalog\BrandService;
use App\Services\Catalog\CarService;
use App\Services\Catalog\CarTypeService;
use App\Services\Media\MediaCollectionRegistry;
use App\Services\Pricing\CurrencyService;
use App\Settings\PricingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-import-type CarData from CarService
 */
class CarController extends Controller
{
    public function __construct(
        private readonly CarService $cars,
        private readonly BrandService $brands,
        private readonly CarTypeService $types,
        private readonly MediaCollectionRegistry $collections,
        private readonly PricingSettings $pricing,
    ) {}

    public function index(IndexCarRequest $request): Response
    {
        return Inertia::render('admin/cars/index', [
            'cars' => CarResource::collection($this->cars->paginate($request->validated())),
            'filters' => $request->validated(),
            'brandOptions' => $this->brands->options(),
            'typeOptions' => $this->types->options(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Car::class);

        return Inertia::render('admin/cars/form', ['car' => null, ...$this->formOptions()]);
    }

    public function store(SaveCarRequest $request): RedirectResponse
    {
        /** @var CarData $data */
        $data = $request->carData();
        $car = $this->cars->create($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$car->name} created. Publish it when it is ready."]);

        return to_route('admin.cars.edit', $car);
    }

    public function edit(Car $car): Response
    {
        Gate::authorize('view', $car);

        return Inertia::render('admin/cars/form', [
            'car' => CarResource::make($car->load(['brand', 'carType', 'gallery', 'blackouts.creator'])),
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveCarRequest $request, Car $car): RedirectResponse
    {
        /** @var CarData $data */
        $data = $request->carData();
        $car = $this->cars->update($car, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Car saved.']);

        return to_route('admin.cars.edit', $car);
    }

    public function publish(Car $car): RedirectResponse
    {
        Gate::authorize('publish', $car);

        $this->cars->publish($car);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$car->name} is live on the site."]);

        return back();
    }

    public function unpublish(Car $car): RedirectResponse
    {
        Gate::authorize('publish', $car);

        $this->cars->unpublish($car);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$car->name} is hidden from the site."]);

        return back();
    }

    public function availability(ToggleCarFlagRequest $request, Car $car): RedirectResponse
    {
        $this->cars->setAvailability($car, $request->boolean('value'));

        return back();
    }

    public function featured(ToggleCarFlagRequest $request, Car $car): RedirectResponse
    {
        $this->cars->setFeatured($car, $request->boolean('value'));

        return back();
    }

    public function destroy(Car $car): RedirectResponse
    {
        Gate::authorize('delete', $car);

        $this->cars->delete($car);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Car deleted.']);

        return to_route('admin.cars.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'brandOptions' => $this->brands->options(),
            'typeOptions' => $this->types->options(),
            'fuelOptions' => array_map(fn (FuelType $type) => ['value' => $type->value, 'label' => $type->label()], FuelType::cases()),
            'transmissionOptions' => array_map(fn (Transmission $type) => ['value' => $type->value, 'label' => $type->label()], Transmission::cases()),
            'blackoutReasons' => array_map(fn (BlackoutReason $reason) => ['value' => $reason->value, 'label' => $reason->label()], BlackoutReason::cases()),
            'currencies' => app(CurrencyService::class)->codes(),
            'defaultMileageKm' => $this->pricing->default_mileage_km,
            'galleryLimits' => $this->collections->get('car.gallery')->toArray(),
        ];
    }
}
