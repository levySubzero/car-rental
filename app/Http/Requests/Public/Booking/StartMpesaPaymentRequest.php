<?php

namespace App\Http\Requests\Public\Booking;

use App\Http\Middleware\ResolveBookingToken;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pay the reservation by M-Pesa STK push (M11B.7): the phone to prompt. Whether it is a
 * Kenyan mobile is checked by OnlinePaymentService.
 */
class StartMpesaPaymentRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Enter the M-Pesa phone number to send the payment request to.',
        ];
    }

    public function booking(): Booking
    {
        return ResolveBookingToken::booking($this);
    }

    public function phone(): string
    {
        return (string) $this->validated('phone');
    }
}
