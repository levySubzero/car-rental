<?php

namespace App\Http\Requests\Admin\PaymentMethod;

use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TogglePaymentMethodActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PaymentMethod $method */
        $method = $this->route('payment_method');

        return $this->user()?->can('update', $method) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'boolean'],
        ];
    }
}
