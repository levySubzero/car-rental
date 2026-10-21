<?php

namespace App\Http\Resources\Admin;

use App\Models\DepositBand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DepositBand
 */
class DepositBandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'percent_bp' => $this->percent_bp,
            'label' => $this->label,
            'is_active' => $this->is_active,
        ];
    }
}
