<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\Media\MediaResource;
use App\Models\Car;
use Illuminate\Http\Request;

/**
 * The car detail page (PRD F-3, F-4): the card plus the gallery and the full info block.
 * Load gallery as well as the card's relations.
 *
 * @mixin Car
 */
class CarDetailResource extends CarCardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'model_year' => $this->model_year,
            'colour' => $this->colour,
            'description' => $this->description,
            'damage_excess' => $this->damageExcess(),
            'gallery' => MediaResource::collection($this->whenLoaded('gallery')),
        ];
    }
}
