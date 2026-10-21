<?php

namespace App\Http\Requests\Admin\Charge;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ChargesUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:charges,id'],
        ];
    }
}
