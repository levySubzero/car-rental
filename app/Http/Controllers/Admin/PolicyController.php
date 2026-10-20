<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PolicyGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Policy\IndexPolicyRequest;
use App\Http\Requests\Admin\Policy\ReorderPoliciesRequest;
use App\Http\Requests\Admin\Policy\SavePolicyDraftRequest;
use App\Http\Requests\Admin\Policy\SavePolicyRequest;
use App\Http\Requests\Admin\Policy\TogglePolicyHomeRequest;
use App\Http\Resources\Admin\PolicyResource;
use App\Models\Policy;
use App\Models\PolicyBundle;
use App\Services\Cms\PolicyBundleService;
use App\Services\Cms\PolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PolicyController extends Controller
{
    public function __construct(
        private readonly PolicyService $policies,
        private readonly PolicyBundleService $bundles,
    ) {}

    public function index(IndexPolicyRequest $request): Response
    {
        return Inertia::render('admin/policies/index', [
            'policies' => PolicyResource::collection($this->policies->ordered()),
            'bundle' => $this->bundleSummary($this->bundles->current()),
            'groupOptions' => $this->groupOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Policy::class);

        return Inertia::render('admin/policies/form', [
            'policy' => null,
            'groupOptions' => $this->groupOptions(),
        ]);
    }

    public function store(SavePolicyRequest $request): RedirectResponse
    {
        $policy = $this->policies->create($request->details(), (string) $request->validated('body'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Policy {$policy->title} created as a draft."]);

        return to_route('admin.policies.edit', $policy);
    }

    public function edit(Policy $policy): Response
    {
        Gate::authorize('view', $policy);

        return Inertia::render('admin/policies/form', [
            'policy' => PolicyResource::make($policy->load(['published', 'draft', 'versions.creator'])),
            'groupOptions' => $this->groupOptions(),
        ]);
    }

    public function update(SavePolicyRequest $request, Policy $policy): RedirectResponse
    {
        $policy = $this->policies->updateDetails($policy, $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Policy details saved.']);

        // The slug may have changed, so go to the policy's current URL.
        return to_route('admin.policies.edit', $policy);
    }

    public function saveDraft(SavePolicyDraftRequest $request, Policy $policy): RedirectResponse
    {
        $draft = $this->policies->saveDraft($policy, (string) $request->validated('body'), $request->user());

        Inertia::flash('toast', $draft === null
            ? ['type' => 'info', 'message' => 'The text matches the published version, so there is no draft.']
            : ['type' => 'success', 'message' => "Draft version {$draft->version} saved. Publish it to make it live."]);

        return back();
    }

    public function discardDraft(Request $request, Policy $policy): RedirectResponse
    {
        Gate::authorize('update', $policy);

        $this->policies->discardDraft($policy);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft discarded.']);

        return back();
    }

    public function publish(Request $request, Policy $policy): RedirectResponse
    {
        Gate::authorize('publish', $policy);

        $version = $this->policies->publish($policy, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Version {$version->version} is live. New bookings accept the updated policy."]);

        return back();
    }

    public function home(TogglePolicyHomeRequest $request, Policy $policy): RedirectResponse
    {
        $this->policies->setShowOnHome($policy, $request->boolean('value'));

        return back();
    }

    public function reorder(ReorderPoliciesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->policies->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Policy order saved.']);

        return back();
    }

    public function destroy(Request $request, Policy $policy): RedirectResponse
    {
        Gate::authorize('delete', $policy);

        $this->policies->delete($policy, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Policy deleted.']);

        return to_route('admin.policies.index');
    }

    /**
     * @return array{version: int, published_at: string|null, policies: int}|null
     */
    private function bundleSummary(?PolicyBundle $bundle): ?array
    {
        return $bundle === null ? null : [
            'version' => $bundle->version,
            'published_at' => $bundle->published_at->toIso8601String(),
            'policies' => $bundle->versions()->count(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function groupOptions(): array
    {
        return array_map(fn (PolicyGroup $group) => ['value' => $group->value, 'label' => $group->label()], PolicyGroup::cases());
    }
}
