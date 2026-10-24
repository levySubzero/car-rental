<?php

namespace App\Http\Requests\Admin\Booking;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cancel or mark a no-show (M11.8): a reason, and the refund staff settle on (D34).
 */
class CancelBookingRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'cancel';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'refund' => $this->amountRules(),
            'refund_override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
