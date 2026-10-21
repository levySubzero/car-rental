<?php

namespace App\Http\Requests\Admin\DeliveryZone;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderDeliveryZonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::DeliveryZonesUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:delivery_zones,id'],
        ];
    }
}
