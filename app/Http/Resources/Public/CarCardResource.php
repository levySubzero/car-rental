<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\Media\MediaResource;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fleet card (PRD F-2). Fields are listed explicitly: admin-only data and the informal
 * extra info never reach the public site. Load brand.logo, carType and cover.
 *
 * @mixin Car
 */
class CarCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'car_type' => CarTypeResource::make($this->whenLoaded('carType')),
            'cover' => $this->whenLoaded('cover', fn () => $this->cover ? MediaResource::make($this->cover) : null),
            'daily_rate' => $this->dailyRate(),
            'engine_type' => $this->engine_type,
            'engine_capacity_litres' => $this->engine_capacity_litres,
            'fuel_type' => $this->fuel_type->label(),
            'transmission' => $this->transmission->label(),
            'doors' => $this->doors,
            'seats' => $this->seats,
            'mileage_limit_km' => $this->effectiveMileageLimitKm(),
            'is_available' => $this->is_available,
        ];
    }
}
