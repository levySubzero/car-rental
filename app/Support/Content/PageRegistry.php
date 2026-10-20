<?php

namespace App\Support\Content;

use App\Enums\ContentFieldType;
use App\Models\Page;
use App\Support\Rules\PendingOrOwnedMedia;

/**
 * The preset pages (D22), read from config/pages.php. Pages are fixed in code: staff edit their
 * content, they never add or remove pages.
 */
final class PageRegistry
{
    /** @var array<string, PageDefinition> */
    private array $pages = [];

    /**
     * @param  array<string, array<string, mixed>>  $definitions  config('pages')
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $slug => $definition) {
            $this->pages[$slug] = PageDefinition::fromConfig($slug, $definition);
        }
    }

    public static function fromConfig(): self
    {
        return new self((array) config('pages'));
    }

    public function has(string $slug): bool
    {
        return isset($this->pages[$slug]);
    }

    public function get(string $slug): ?PageDefinition
    {
        return $this->pages[$slug] ?? null;
    }

    /**
     * @return array<string, PageDefinition>
     */
    public function all(): array
    {
        return $this->pages;
    }

    /**
     * Validation rules for a page's `content` input. Fields are never required to save:
     * staff may fill a page in several sittings (see `required` in config/pages.php).
     *
     * @return array<string, mixed>
     */
    public function rulesFor(PageDefinition $page, ?Page $owner = null): array
    {
        $rules = ['content' => ['required', 'array']];

        foreach ($page->fields as $field) {
            $key = "content.{$field->key}";

            $rules[$key] = match ($field->type) {
                ContentFieldType::Text, ContentFieldType::Markdown => ['nullable', 'string', "max:{$field->max}"],
                ContentFieldType::Image => ['nullable', 'uuid', new PendingOrOwnedMedia('page.image', $owner)],
                ContentFieldType::List => ['present', 'array', "size:{$field->items}"],
            };

            foreach ($field->fields as $sub) {
                $rules["{$key}.*.{$sub->key}"] = ['nullable', 'string', "max:{$sub->max}"];
            }
        }

        return $rules;
    }
}
