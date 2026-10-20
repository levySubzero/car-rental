<?php

namespace App\Http\Resources\Public;

use App\Models\Brand;
use App\Services\Media\MediaUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand on the public site (marquee, filters, car cards). Load `logo` to include it.
 *
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
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->whenLoaded('logo', fn () => $this->logo
                ? app(MediaUrlGenerator::class)->urlsFor($this->logo)
                : null),
        ];
    }
}
