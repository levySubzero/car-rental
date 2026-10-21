<?php

namespace App\Http\Resources\Admin;

use App\Models\FxRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the rate history.
 *
 * @mixin FxRate
 */
class FxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => $this->currency_code,
            'market_rate' => $this->market_rate,
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'provider' => $this->provider,
            'fetched_at' => $this->fetched_at->toIso8601String(),
            'override_until' => $this->override_until?->toIso8601String(),
            'override_by' => $this->whenLoaded('overrider', fn () => $this->overrider?->name),
            'is_live_override' => $this->source->value === 'manual'
                && ($this->override_until === null || $this->override_until->isFuture()),
        ];
    }
}
