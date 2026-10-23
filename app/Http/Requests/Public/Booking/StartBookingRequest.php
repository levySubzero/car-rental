<?php

namespace App\Http\Requests\Public\Booking;

use App\Http\Requests\Public\Booking\Concerns\ValidatesTrip;
use App\Support\Rules\Turnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Starts a draft from the booking page with the trip filled in (M8.9). Open to any visitor:
 * Turnstile and the `booking-start` limiter keep bots out.
 */
class StartBookingRequest extends FormRequest
{
    use ValidatesTrip;

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
            ...$this->tripRules(),
            'cf-turnstile-response' => [new Turnstile($this->ip())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tripMessages();
    }
}
