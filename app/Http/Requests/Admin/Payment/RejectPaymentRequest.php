<?php

namespace App\Http\Requests\Admin\Payment;

use App\Models\PaymentSubmission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The renter sees the reason and can pay again (M9.5).
 */
class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('payment');

        return $submission instanceof PaymentSubmission && ($this->user()?->can('reject', $submission) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
