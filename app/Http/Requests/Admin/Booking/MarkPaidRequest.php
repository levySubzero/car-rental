<?php

namespace App\Http\Requests\Admin\Booking;

use App\Enums\PaymentMethodCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Mark a booking paid from its page: what was received, how, and whether the voucher goes
 * to the renter straight away.
 */
class MarkPaidRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'markPaid';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000', 'decimal:0,2'],
            'method' => ['required', Rule::enum(PaymentMethodCode::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'send_voucher' => ['required', 'boolean'],
        ];
    }

    public function paymentMethod(): PaymentMethodCode
    {
        return PaymentMethodCode::from((string) $this->validated('method'));
    }
}
