<?php

namespace App\Http\Requests\Public\Booking;

use App\Http\Middleware\ResolveBookingToken;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Paystack popup reported a charge (M11B.7): its reference, which the server verifies with
 * Paystack before believing anything.
 */
class ConfirmCardPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

    public function booking(): Booking
    {
        return ResolveBookingToken::booking($this);
    }

    public function reference(): string
    {
        return (string) $this->validated('reference');
    }
}
