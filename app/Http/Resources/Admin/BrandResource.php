<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Media\MediaResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'show_in_marquee' => $this->show_in_marquee,
            'is_active' => $this->is_active,
            'cars_count' => $this->whenCounted('cars'),
            'logo' => $this->whenLoaded('logo', fn () => $this->logo ? MediaResource::make($this->logo) : null),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
