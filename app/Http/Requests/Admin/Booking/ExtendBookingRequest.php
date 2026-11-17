<?php

namespace App\Http\Requests\Admin\Booking;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Move a confirmed or running hire's return. The same rules serve the live preview, which
 * ignores `total`.
 */
class ExtendBookingRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'extend';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'return_at' => ['required', 'date', 'after:'.$this->booking()->pickup_at->toIso8601String(), 'before:+1 year'],
            'total' => $this->amountRules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'return_at.after' => 'The return must be after the pickup.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tripData(): array
    {
        return ['return_at' => $this->date('return_at')?->utc()];
    }

    public function total(): ?Money
    {
        return filled($this->validated('total')) ? $this->money('total') : null;
    }
}
