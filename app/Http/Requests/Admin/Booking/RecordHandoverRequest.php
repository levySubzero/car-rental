<?php

namespace App\Http\Requests\Admin\Booking;

use App\Enums\FuelLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class RecordHandoverRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'recordHandover';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'odometer_km' => ['required', 'integer', 'min:0', 'max:2000000'],
            'fuel_level' => ['required', Rule::enum(FuelLevel::class)],
            'balance_received' => $this->amountRules(),
            'deposit_received' => $this->amountRules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
