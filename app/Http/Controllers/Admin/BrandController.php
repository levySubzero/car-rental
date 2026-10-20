<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Brand\IndexBrandRequest;
use App\Http\Requests\Admin\Brand\ReorderBrandsRequest;
use App\Http\Requests\Admin\Brand\SaveBrandRequest;
use App\Http\Requests\Admin\Brand\ToggleBrandMarqueeRequest;
use App\Http\Resources\Admin\BrandResource;
use App\Models\Brand;
use App\Services\Catalog\BrandService;
use App\Services\Media\MediaCollectionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brands,
        private readonly MediaCollectionRegistry $collections,
    ) {}

    public function index(IndexBrandRequest $request): Response
    {
        return Inertia::render('admin/brands/index', [
            'brands' => BrandResource::collection($this->brands->paginate($request->validated())),
            'filters' => $request->validated(),
            'marquee' => BrandResource::collection($this->brands->ordered()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Brand::class);

        return Inertia::render('admin/brands/form', [
            'brand' => null,
            'logoLimits' => $this->collections->get('brand.logo')->toArray(),
        ]);
    }

    public function store(SaveBrandRequest $request): RedirectResponse
    {
        /** @var array{name: string, slug?: string|null, show_in_marquee?: bool, is_active?: bool, logo?: string|null} $data */
        $data = $request->validated();
        $brand = $this->brands->create($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Brand {$brand->name} created."]);

        return to_route('admin.brands.edit', $brand);
    }

    public function edit(Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('admin/brands/form', [
            'brand' => BrandResource::make($brand->load('logo')->loadCount('cars')),
            'logoLimits' => $this->collections->get('brand.logo')->toArray(),
        ]);
    }

    public function update(SaveBrandRequest $request, Brand $brand): RedirectResponse
    {
        /** @var array{name: string, slug?: string|null, show_in_marquee?: bool, is_active?: bool, logo?: string|null} $data */
        $data = $request->validated();
        $brand = $this->brands->update($brand, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Brand saved.']);

        // The slug may have changed, so go to the brand's current URL.
        return to_route('admin.brands.edit', $brand);
    }

    public function marquee(ToggleBrandMarqueeRequest $request, Brand $brand): RedirectResponse
    {
        $this->brands->setInMarquee($brand, $request->boolean('value'));

        return back();
    }

    public function reorder(ReorderBrandsRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->brands->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marquee order saved.']);

        return back();
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);

        $this->brands->delete($brand);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Brand deleted.']);

        return to_route('admin.brands.index');
    }
}
