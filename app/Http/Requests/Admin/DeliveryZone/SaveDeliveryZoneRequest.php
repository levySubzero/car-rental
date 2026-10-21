<?php

namespace App\Http\Requests\Admin\DeliveryZone;

use App\Models\DeliveryZone;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a delivery zone, or updates the one bound to the route. An empty fee means staff
 * quote it per booking (D29).
 */
class SaveDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $zone = $this->zone();

        return $zone === null
            ? ($this->user()?->can('create', DeliveryZone::class) ?? false)
            : ($this->user()?->can('update', $zone) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('delivery_zones', 'name')->ignore($this->zone())],
            'description' => ['nullable', 'string', 'max:1000'],
            'fee' => ['nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, fee_minor: int|null, is_default: bool, is_active: bool}
     */
    public function zoneData(): array
    {
        $fee = $this->validated('fee');

        return [
            'name' => (string) $this->validated('name'),
            'description' => $this->validated('description'),
            'fee_minor' => filled($fee) ? Money::fromMajor((string) $fee)->minor : null,
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function zone(): ?DeliveryZone
    {
        $zone = $this->route('delivery_zone');

        return $zone instanceof DeliveryZone ? $zone : null;
    }
}
