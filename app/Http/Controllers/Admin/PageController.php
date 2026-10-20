<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Page\UpdatePageRequest;
use App\Http\Resources\Admin\PageResource;
use App\Models\Page;
use App\Services\Cms\PageService;
use App\Services\Media\MediaCollectionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Preset pages (D22): a list and an editor. Pages are defined in config/pages.php, so
 * there is no create or delete.
 */
class PageController extends Controller
{
    public function __construct(
        private readonly PageService $pages,
        private readonly MediaCollectionRegistry $collections,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Page::class);

        return Inertia::render('admin/pages/index', [
            'pages' => PageResource::collection($this->pages->all()->load('editor')),
        ]);
    }

    public function edit(Page $page): Response
    {
        Gate::authorize('view', $page);
        $this->pages->definitionFor($page);

        return Inertia::render('admin/pages/edit', [
            'page' => PageResource::make($page->load(['media', 'editor'])),
            'imageLimits' => $this->collections->get('page.image')->toArray(),
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        /** @var array<string, mixed> $content */
        $content = $request->validated('content');
        /** @var array{meta_title?: string|null, meta_description?: string|null} $meta */
        $meta = $request->safe()->only(['meta_title', 'meta_description']);

        $this->pages->update($page, $content, $meta, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Page saved.']);

        return to_route('admin.pages.edit', $page);
    }
}
