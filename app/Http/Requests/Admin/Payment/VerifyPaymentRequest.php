<?php

namespace App\Http\Requests\Admin\Payment;

use App\Models\PaymentSubmission;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff confirm a payment with the reference on their statement and the amount received,
 * in KES (M9.5). Less than the reservation is checked by the service.
 */
class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('payment');

        return $submission instanceof PaymentSubmission && ($this->user()?->can('verify', $submission) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function amount(): Money
    {
        return Money::fromMajor((string) $this->validated('amount'));
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }
}
