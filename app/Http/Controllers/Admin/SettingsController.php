<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingsGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSettingsRequest;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): RedirectResponse
    {
        $first = collect($this->tabsFor($request->user()))->first();
        abort_if($first === null, 403);

        return to_route('admin.settings.edit', $first['key']);
    }

    public function edit(Request $request, SettingsGroup $group): Response
    {
        abort_unless($group->isEditable(), 404);

        return Inertia::render('admin/settings/edit', [
            'group' => $group->value,
            'tabs' => $this->tabsFor($request->user()),
            'values' => $this->settings->values($group),
            'context' => $this->settings->context($group),
        ]);
    }

    public function update(SettingsGroup $group): RedirectResponse
    {
        abort_unless($group->isEditable(), 404);

        /** @var UpdateSettingsRequest $request */
        $request = app((string) $group->requestClass());

        $this->settings->update($group, $request->settingsData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$group->label()} settings saved."]);

        return back();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function tabsFor(?User $user): array
    {
        return collect(SettingsGroup::cases())
            ->filter(fn (SettingsGroup $group) => $group->isEditable() && $user?->can($group->permission()))
            ->map(fn (SettingsGroup $group) => ['key' => $group->value, 'label' => $group->label()])
            ->values()
            ->all();
    }
}
