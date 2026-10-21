<?php

namespace App\Http\Resources\Admin;

use App\Models\Currency;
use App\Services\Pricing\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
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
            'symbol' => $this->symbol,
            'decimals' => $this->decimals,
            'is_active' => $this->is_active,
            'is_base' => app(CurrencyService::class)->isBase($this->resource),
            'sort_order' => $this->sort_order,
        ];
    }
}
