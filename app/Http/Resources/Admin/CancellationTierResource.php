<?php

namespace App\Http\Resources\Admin;

use App\Models\CancellationTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CancellationTier
 */
class CancellationTierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hours_before_pickup' => $this->hours_before_pickup,
            'refund_percent_bp' => $this->refund_percent_bp,
            'label' => $this->label,
        ];
    }
}
