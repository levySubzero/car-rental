<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Media\MediaResource;
use App\Models\Car;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything staff edit, including the extra info. The car value is only included for
 * staff with cars.view-value.
 *
 * @mixin Car
 */
class CarResource extends JsonResource
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
            'brand_id' => $this->brand_id,
            'car_type_id' => $this->car_type_id,
            'brand' => $this->whenLoaded('brand', fn () => ['id' => $this->brand->id, 'name' => $this->brand->name]),
            'car_type' => $this->whenLoaded('carType', fn () => ['id' => $this->carType->id, 'name' => $this->carType->name]),
            'model_year' => $this->model_year,
            'engine_type' => $this->engine_type,
            'engine_capacity_litres' => $this->engine_capacity_litres,
            'fuel_type' => $this->fuel_type->value,
            'transmission' => $this->transmission->value,
            'colour' => $this->colour,
            'doors' => $this->doors,
            'seats' => $this->seats,
            'daily_rate' => $this->dailyRate(),
            'car_value' => $this->when(
                $request->user()?->can('viewValue', $this->resource) ?? false,
                fn () => $this->car_value_minor === null ? null : new Money($this->car_value_minor),
            ),
            'mileage_limit_km' => $this->mileage_limit_km,
            'damage_excess' => $this->damageExcess(),
            'is_featured' => $this->is_featured,
            'is_available' => $this->is_available,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'description' => $this->description,
            'metadata' => (object) ($this->metadata?->getArrayCopy() ?? []),
            'cover' => $this->whenLoaded('cover', fn () => $this->cover ? MediaResource::make($this->cover) : null),
            'gallery' => MediaResource::collection($this->whenLoaded('gallery')),
            'blackouts' => CarBlackoutResource::collection($this->whenLoaded('blackouts')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
