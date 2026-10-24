<?php

namespace App\Http\Requests\Admin\Booking;

use Illuminate\Contracts\Validation\ValidationRule;

class LegalHoldRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'legalHold';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_hold' => ['required', 'boolean'],
            'reason' => ['required_if_accepted:legal_hold', 'nullable', 'string', 'max:1000'],
        ];
    }
}
