<?php

namespace App\Http\Requests\Admin\Receipt;

use App\Models\GatewayTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', GatewayTransaction::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'in:open,all'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
