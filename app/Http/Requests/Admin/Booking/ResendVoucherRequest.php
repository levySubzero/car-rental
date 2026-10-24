<?php

namespace App\Http\Requests\Admin\Booking;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Resend the voucher, or replace the renter's link (M11.5).
 */
class ResendVoucherRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'resendVoucher';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
