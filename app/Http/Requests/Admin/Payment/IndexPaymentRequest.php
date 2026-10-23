<?php

namespace App\Http\Requests\Admin\Payment;

use App\Enums\PaymentStatus;
use App\Models\PaymentSubmission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PaymentSubmission::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ];
    }
}
