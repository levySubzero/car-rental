<?php

namespace App\Http\Requests\Admin\CarType;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCarTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CarTypesUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:car_types,id'],
        ];
    }
}
