<?php

namespace App\Http\Requests\Admin\Service;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ServicesUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:services,id'],
        ];
    }
}
