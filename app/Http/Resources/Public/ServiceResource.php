<?php

namespace App\Http\Resources\Public;

use App\Models\Service;
use App\Services\Media\MediaUrlGenerator;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service on the public site. The body is rendered from Markdown here (D23); load `image` to include it.
 *
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
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'body_html' => Markdown::toHtml($this->body),
            'cta' => [
                'type' => $this->cta_type->value,
                'value' => $this->cta_value,
            ],
            'image' => $this->whenLoaded('image', fn () => $this->image
                ? app(MediaUrlGenerator::class)->urlsFor($this->image)
                : null),
        ];
    }
}
