<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Integration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Integrations\UpdateIntegrationRequest;
use App\Services\Integrations\IntegrationConfig;
use App\Services\Integrations\IntegrationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The keys and settings of each third-party service (M12B.15, D46).
 */
class IntegrationController extends Controller
{
    public function __construct(private readonly IntegrationService $integrations) {}

    public function index(): RedirectResponse
    {
        return to_route('admin.integrations.edit', Integration::cases()[0]);
    }

    public function edit(Integration $integration, IntegrationConfig $config): Response
    {
        return Inertia::render('admin/integrations/edit', [
            'integration' => [
                'key' => $integration->value,
                'label' => $integration->label(),
                'description' => $integration->description(),
                'paystack_mode' => $integration === Integration::Paystack ? $this->integrations->paystackMode() : null,
            ],
            'integrations' => array_map(fn (Integration $case) => ['value' => $case->value, 'label' => $case->label()], Integration::cases()),
            'fields' => $this->integrations->fields($integration),
            'unreadable' => $config->isUnreadable(),
        ]);
    }

    public function update(UpdateIntegrationRequest $request, Integration $integration): RedirectResponse
    {
        $changed = $this->integrations->update($integration, $request->credentials(), $request->clear(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changed === [] ? 'Nothing to change.' : "{$integration->label()} saved.",
        ]);

        return back();
    }
}
