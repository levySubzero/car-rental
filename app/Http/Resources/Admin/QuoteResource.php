<?php

namespace App\Http\Resources\Admin;

use App\Enums\Permission;
use App\Services\Pricing\Quote\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full quote for staff, as a booking would snapshot it. The deposit and its band are
 * left out for staff without cars.view-value: together they reveal the car's value.
 *
 * @property Quote $resource
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        if ($request->user()?->can(Permission::CarsViewValue)) {
            return $data;
        }

        return [
            ...$data,
            'deposit' => null,
            'deposit_band' => null,
            'deposit_hidden' => true,
            'display' => array_map(fn (array $amounts) => [...$amounts, 'deposit' => null], $data['display']),
        ];
    }
}
