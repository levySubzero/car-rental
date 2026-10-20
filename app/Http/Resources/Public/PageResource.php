<?php

namespace App\Http\Resources\Public;

use App\Enums\ContentFieldType;
use App\Models\Media;
use App\Models\Page;
use App\Services\Media\MediaUrlGenerator;
use App\Support\Content\PageRegistry;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A preset page's content for the public site, field by field: text as is, Markdown as
 * `{key}_html` (D23), images as their URLs. Load `media` so images resolve.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $definition = app(PageRegistry::class)->get($this->slug);
        $content = $definition?->fill($this->content) ?? [];
        $images = $this->relationLoaded('media') ? $this->media->keyBy('uuid') : collect();
        $urls = app(MediaUrlGenerator::class);
        $fields = [];

        foreach ($definition->fields ?? [] as $field) {
            $value = $content[$field->key];

            match ($field->type) {
                ContentFieldType::Markdown => $fields["{$field->key}_html"] = Markdown::toHtml($value),
                ContentFieldType::Image => $fields[$field->key] = ($media = $images->get($value)) instanceof Media
                    ? ['urls' => $urls->urlsFor($media), 'alt' => $media->metadata['alt'] ?? null]
                    : null,
                default => $fields[$field->key] = $value,
            };
        }

        return [
            'slug' => $this->slug,
            'meta' => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
            ],
            'content' => $fields,
        ];
    }
}
