<?php

namespace App\Http\Resources\Media;

use App\Models\Media;
use App\Services\Media\MediaUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One file, for the uploader and every page that shows media. The uuid is the only
 * identifier; the disk and path never leave the server. Mirrors resources/js/types/media.ts.
 *
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'collection' => $this->collection,
            'name' => $this->original_name,
            'mime' => $this->mime_type,
            'size' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->metadata['alt'] ?? null,
            'caption' => $this->metadata['caption'] ?? null,
            'visibility' => $this->visibility->value,
            'status' => $this->status->value,
            'processing' => $this->processing->value,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
            'urls' => app(MediaUrlGenerator::class)->urlsFor($this->resource),
        ];
    }
}
