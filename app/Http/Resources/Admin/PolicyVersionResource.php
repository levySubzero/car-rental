<?php

namespace App\Http\Resources\Admin;

use App\Models\PolicyVersion;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PolicyVersion
 */
class PolicyVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'body' => $this->body,
            'has_confirm' => Markdown::hasConfirm($this->body),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator->name ?? 'Seed pack'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
