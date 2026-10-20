<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceCtaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\IndexServiceRequest;
use App\Http\Requests\Admin\Service\ReorderServicesRequest;
use App\Http\Requests\Admin\Service\SaveServiceRequest;
use App\Http\Requests\Admin\Service\ToggleServiceActiveRequest;
use App\Http\Resources\Admin\ServiceResource;
use App\Models\Service;
use App\Services\Cms\ServiceService;
use App\Services\Media\MediaCollectionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceService $services,
        private readonly MediaCollectionRegistry $collections,
    ) {}

    public function index(IndexServiceRequest $request): Response
    {
        return Inertia::render('admin/services/index', [
            'services' => ServiceResource::collection($this->services->ordered()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Service::class);

        return Inertia::render('admin/services/form', [
            'service' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveServiceRequest $request): RedirectResponse
    {
        $service = $this->services->create($request->serviceData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Service {$service->title} created."]);

        return to_route('admin.services.edit', $service);
    }

    public function edit(Service $service): Response
    {
        Gate::authorize('view', $service);

        return Inertia::render('admin/services/form', [
            'service' => ServiceResource::make($service->load('image')),
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveServiceRequest $request, Service $service): RedirectResponse
    {
        $service = $this->services->update($service, $request->serviceData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service saved.']);

        // The slug may have changed, so go to the service's current URL.
        return to_route('admin.services.edit', $service);
    }

    public function active(ToggleServiceActiveRequest $request, Service $service): RedirectResponse
    {
        $this->services->setActive($service, $request->boolean('value'));

        return back();
    }

    public function reorder(ReorderServicesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->services->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service order saved.']);

        return back();
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $this->services->delete($service);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service deleted.']);

        return to_route('admin.services.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'imageLimits' => $this->collections->get('service.image')->toArray(),
            'ctaOptions' => array_map(fn (ServiceCtaType $type) => ['value' => $type->value, 'label' => $type->label()], ServiceCtaType::cases()),
        ];
    }
}
