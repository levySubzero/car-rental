<?php

namespace App\Http\Requests\Admin\CarType;

use App\Models\CarType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a car type, or updates the one bound to the route.
 */
class SaveCarTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->carType();

        return $type === null
            ? ($this->user()?->can('create', CarType::class) ?? false)
            : ($this->user()?->can('update', $type) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('car_types', 'name')->ignore($this->carType())],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('car_types', 'slug')->ignore($this->carType())],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function carType(): ?CarType
    {
        $type = $this->route('car_type');

        return $type instanceof CarType ? $type : null;
    }
}
