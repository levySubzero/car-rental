<?php

namespace App\Http\Requests\Admin\Booking;

use App\Services\Notifications\VoucherService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Staff's edits to a voucher's text. They change the voucher only, not the booking.
 */
class UpdateVoucherRequest extends BookingActionRequest
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
        $fields = array_fill_keys(array_keys(VoucherService::EDITABLE), ['nullable', 'string', 'max:255']);

        return [...$fields, 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
