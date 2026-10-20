<?php

namespace App\Services\Cms;

use App\Models\Page;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Support\Content\PageDefinition;
use App\Support\Content\PageRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Content of the preset pages (D22). The registry says which pages and fields exist;
 * this service stores the values.
 */
class PageService
{
    public function __construct(
        private readonly PageRegistry $registry,
        private readonly MediaService $media,
    ) {}

    /**
     * Every preset page, in registry order. Rows that do not exist yet are created empty.
     *
     * @return Collection<int, Page>
     */
    public function all(): Collection
    {
        $this->ensureAll();

        $order = array_flip(array_keys($this->registry->all()));

        return Page::query()
            ->whereIn('slug', array_keys($order))
            ->get()
            ->sortBy(fn (Page $page) => $order[$page->slug])
            ->values();
    }

    /**
     * Create a row for every preset page that has none. Existing content is left alone.
     *
     * @return int the number of pages created
     */
    public function ensureAll(): int
    {
        $existing = Page::query()->pluck('slug')->all();
        $created = 0;

        foreach ($this->registry->all() as $definition) {
            if (! in_array($definition->slug, $existing, true)) {
                Page::create(['slug' => $definition->slug, 'content' => $definition->fill(null)]);
                $created++;
            }
        }

        return $created;
    }

    public function definitionFor(Page $page): PageDefinition
    {
        return $this->registry->get($page->slug) ?? abort(404);
    }

    /**
     * Save a page's content. Unknown keys are dropped, and the image fields become the page's
     * `page.image` files: images no longer referenced are removed.
     *
     * @param  array<string, mixed>  $content
     * @param  array{meta_title?: string|null, meta_description?: string|null}  $meta
     */
    public function update(Page $page, array $content, array $meta, User $actor): Page
    {
        $definition = $this->definitionFor($page);
        $content = $definition->fill($content);

        return DB::transaction(function () use ($page, $definition, $content, $meta, $actor) {
            $this->media->sync($page, 'page.image', $definition->imageUuids($content), actor: $actor);

            $page->update([
                'content' => $content,
                'meta_title' => $meta['meta_title'] ?? null,
                'meta_description' => $meta['meta_description'] ?? null,
                'updated_by' => $actor->id,
            ]);

            return $page;
        });
    }

    /**
     * Labels of required fields still empty on a page, for the "needs content" badge and the
     * dashboard's "Before launch" list.
     *
     * @return list<string>
     */
    public function missing(Page $page): array
    {
        return $this->definitionFor($page)->missing($page->content);
    }
}
