<?php

namespace App\Http\Requests\Admin\Receipt;

use App\Enums\GatewayTransactionStatus;
use App\Models\GatewayTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Close a receipt without applying it (M11B.9): refunded to the payer, or closed with a note.
 */
class ResolveReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('receipt');

        return $receipt instanceof GatewayTransaction && ($this->user()?->can('resolve', $receipt) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in([GatewayTransactionStatus::Refunded->value, GatewayTransactionStatus::Ignored->value])],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Say what was done: the refund reference, or why it is closed.',
        ];
    }

    public function outcome(): GatewayTransactionStatus
    {
        return GatewayTransactionStatus::from((string) $this->validated('outcome'));
    }
}
