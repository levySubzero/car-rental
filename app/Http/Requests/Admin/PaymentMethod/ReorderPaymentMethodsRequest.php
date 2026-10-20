<?php

namespace App\Http\Requests\Admin\PaymentMethod;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderPaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::PaymentMethodsUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:payment_methods,id'],
        ];
    }
}
