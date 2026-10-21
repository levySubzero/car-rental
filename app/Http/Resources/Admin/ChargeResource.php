<?php

namespace App\Http\Resources\Admin;

use App\Models\Charge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Charge
 */
class ChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'basis' => $this->basis->value,
            'basis_label' => $this->basis->label(),
            'amount' => $this->amount(),
            'currency_code' => $this->currency_code,
            'tax_inclusive' => $this->tax_inclusive,
            'payable_at' => $this->payable_at->value,
            'payable_at_label' => $this->payable_at->label(),
            'applies' => $this->applies->value,
            'applies_label' => $this->applies->label(),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
