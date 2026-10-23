<?php

namespace App\Http\Requests\Public\Booking;

use App\Http\Requests\Public\Booking\Concerns\ValidatesTrip;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changes to step 1 of a draft. The booking token is the authorization (`booking.token`).
 */
class SaveTripStepRequest extends FormRequest
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
        return $this->tripRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tripMessages();
    }
}
