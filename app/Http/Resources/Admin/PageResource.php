<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\Page;
use App\Support\Content\PageRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A preset page with its field definitions, for the admin editor. Load `media` to include
 * the image fields' files, keyed by uuid.
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

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $definition->title ?? $this->slug,
            'description' => $definition?->description,
            'fields' => $this->when($this->relationLoaded('media'), fn () => $definition?->toArray()['fields'] ?? []),
            'content' => $definition?->fill($this->content) ?? [],
            'missing' => $definition?->missing($this->content) ?? [],
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'images' => $this->whenLoaded('media', fn () => $this->media
                ->where('collection', 'page.image')
                ->mapWithKeys(fn (Media $media) => [$media->uuid => MediaResource::make($media)])),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->editor?->name),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
