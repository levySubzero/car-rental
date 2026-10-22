<?php

namespace App\Services\Cms;

use App\Http\Resources\Public\PageResource;
use App\Models\Page;

/**
 * A preset page's content as the public site uses it: text as is, Markdown as `{key}_html`,
 * images as `{urls, alt}` (PageResource). Empty when the page row does not exist yet.
 */
class PageContent
{
    /**
     * @return array<string, mixed>
     */
    public function for(string $slug): array
    {
        $page = Page::query()->with('media')->firstWhere('slug', $slug);

        return $page ? PageResource::make($page)->resolve()['content'] : [];
    }

    /**
     * A text field, or the fallback while staff have left it empty.
     *
     * @param  array<string, mixed>  $content
     */
    public static function text(array $content, string $key, ?string $fallback = null): ?string
    {
        $value = $content[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : $fallback;
    }
}
