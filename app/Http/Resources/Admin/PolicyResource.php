<?php

namespace App\Http\Resources\Admin;

use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A policy for the admin. Load `published` and `draft` for the list, and `versions.creator`
 * for the history on the edit page.
 *
 * @mixin Policy
 */
class PolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'group' => $this->group->value,
            'group_label' => $this->group->label(),
            'summary' => $this->summary,
            'show_on_home' => $this->show_on_home,
            'sort_order' => $this->sort_order,
            'published' => $this->whenLoaded('published', fn () => $this->published ? PolicyVersionResource::make($this->published) : null),
            'draft' => $this->whenLoaded('draft', fn () => $this->draft ? PolicyVersionResource::make($this->draft) : null),
            'versions' => PolicyVersionResource::collection($this->whenLoaded('versions')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
