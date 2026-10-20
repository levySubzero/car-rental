<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarType\ReorderCarTypesRequest;
use App\Http\Requests\Admin\CarType\SaveCarTypeRequest;
use App\Http\Resources\Admin\CarTypeResource;
use App\Models\CarType;
use App\Services\Catalog\CarTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CarTypeController extends Controller
{
    public function __construct(private readonly CarTypeService $types) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', CarType::class);

        return Inertia::render('admin/car-types/index', [
            'types' => CarTypeResource::collection($this->types->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', CarType::class);

        return Inertia::render('admin/car-types/form', ['type' => null]);
    }

    public function store(SaveCarTypeRequest $request): RedirectResponse
    {
        /** @var array{name: string, slug?: string|null, description?: string|null, is_active?: bool} $data */
        $data = $request->validated();
        $type = $this->types->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Car type {$type->name} created."]);

        return to_route('admin.car-types.index');
    }

    public function edit(CarType $carType): Response
    {
        Gate::authorize('view', $carType);

        return Inertia::render('admin/car-types/form', [
            'type' => CarTypeResource::make($carType->loadCount('cars')),
        ]);
    }

    public function update(SaveCarTypeRequest $request, CarType $carType): RedirectResponse
    {
        /** @var array{name: string, slug?: string|null, description?: string|null, is_active?: bool} $data */
        $data = $request->validated();
        $this->types->update($carType, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Car type saved.']);

        return to_route('admin.car-types.index');
    }

    public function reorder(ReorderCarTypesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->types->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order saved.']);

        return back();
    }

    public function destroy(CarType $carType): RedirectResponse
    {
        Gate::authorize('delete', $carType);

        $this->types->delete($carType);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Car type deleted.']);

        return to_route('admin.car-types.index');
    }
}
