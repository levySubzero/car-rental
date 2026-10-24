<?php

namespace App\Http\Requests\Admin\Booking;

use Illuminate\Contracts\Validation\ValidationRule;

class MarkRefundedRequest extends BookingActionRequest
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
            'reference' => ['required', 'string', 'max:100'],
        ];
    }
}
