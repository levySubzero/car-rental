<?php

namespace App\Http\Requests\Admin\Booking;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accept a request, optionally setting its total and security deposit.
 */
class AcceptBookingRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'accept';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total' => $this->amountRules(required: false),
            'deposit' => $this->amountRules(required: false),
        ];
    }

    public function optionalMoney(string $key): ?Money
    {
        return filled($this->validated($key)) ? $this->money($key) : null;
    }
}
