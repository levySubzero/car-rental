<?php

namespace App\Http\Requests\Admin\Booking;

use Illuminate\Contracts\Validation\ValidationRule;

class MarkDeliveryCheckedRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'updateDelivery';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
