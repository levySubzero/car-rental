<?php

namespace App\Http\Requests\Admin\Currency;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCurrenciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CurrenciesUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:currencies,id'],
        ];
    }
}
