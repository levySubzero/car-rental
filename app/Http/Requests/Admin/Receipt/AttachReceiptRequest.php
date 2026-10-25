<?php

namespace App\Http\Requests\Admin\Receipt;

use App\Models\Booking;
use App\Models\GatewayTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Apply an unmatched or held-back receipt to the booking it was meant for (M11B.9).
 */
class AttachReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('receipt');

        return $receipt instanceof GatewayTransaction && ($this->user()?->can('resolve', $receipt) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['booking' => strtoupper(trim((string) $this->input('booking')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'booking' => ['required', 'string', 'max:20', 'exists:bookings,reference'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'booking.required' => 'Enter the booking reference, e.g. BE-2026-000123.',
            'booking.exists' => 'No booking has this reference.',
        ];
    }

    public function booking(): Booking
    {
        return Booking::query()->where('reference', $this->validated('booking'))->firstOrFail();
    }
}
