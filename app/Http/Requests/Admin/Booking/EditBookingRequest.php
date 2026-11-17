<?php

namespace App\Http\Requests\Admin\Booking;

use App\Http\Requests\Public\Booking\Concerns\ValidatesTrip;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Change a booking's trip before handover: car, dates, handover and extras. The same rules
 * serve the live preview, which ignores `total`.
 */
class EditBookingRequest extends BookingActionRequest
{
    use ValidatesTrip;

    protected function ability(): string
    {
        return 'edit';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->tripRules(),
            // Staff may correct a booking whose pickup is today or already logged.
            'pickup_at' => ['required', 'date'],
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')->whereNull('deleted_at')],
            'total' => $this->amountRules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tripMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return [...$this->tripData(), 'car_id' => (int) $this->validated('car_id')];
    }

    public function total(): ?Money
    {
        return filled($this->validated('total')) ? $this->money('total') : null;
    }
}
