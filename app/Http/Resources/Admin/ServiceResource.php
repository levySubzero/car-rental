<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Media\MediaResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
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
            'summary' => $this->summary,
            'body' => $this->body,
            'cta_type' => $this->cta_type->value,
            'cta_label' => $this->cta_type->label(),
            'cta_value' => $this->cta_value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'image' => $this->whenLoaded('image', fn () => $this->image ? MediaResource::make($this->image) : null),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
