<?php

namespace App\Http\Resources\Public;

use App\Models\Policy;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A policy's live text for the public site: the published version, rendered from Markdown (D23).
 * Load `published`; drafts never reach the public site.
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
            'title' => $this->title,
            'slug' => $this->slug,
            'group' => $this->group->value,
            'summary' => $this->summary,
            'version' => $this->published?->version,
            'body_html' => Markdown::toHtml($this->published?->body),
        ];
    }
}
