<?php

namespace App\Http\Requests\Admin\PaymentMethod;

use App\Models\PaymentMethod;
use App\Support\Rules\MetadataKeys;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePaymentMethodRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'instructions' => ['required', 'string', 'max:5000'],
            'requires_transaction_message' => ['required', 'boolean'],
            'requires_proof' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            // M-Pesa and card only (M11B.8); ignored for the others.
            'online_enabled' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array', 'max:30', new MetadataKeys],
            'metadata.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'metadata.*' => 'detail value',
        ];
    }

    /**
     * Empty details are dropped (D20). Only M-Pesa and card can go online.
     *
     * @return array{name: string, instructions: string, requires_transaction_message: bool, requires_proof: bool, is_active: bool, online_enabled?: bool, metadata?: array<string, string>|null}
     */
    public function methodData(): array
    {
        $data = $this->validated();

        /** @var PaymentMethod $method */
        $method = $this->route('payment_method');

        if (! $method->code->supportsOnline()) {
            unset($data['online_enabled']);
        }

        $data['metadata'] = array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data['metadata'] ?? []),
            fn ($value) => filled($value),
        );

        /** @var array{name: string, instructions: string, requires_transaction_message: bool, requires_proof: bool, is_active: bool, online_enabled?: bool, metadata?: array<string, string>|null} */
        return $data;
    }
}
