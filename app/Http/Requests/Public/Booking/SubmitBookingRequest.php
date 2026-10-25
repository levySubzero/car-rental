<?php

namespace App\Http\Requests\Public\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Submit after reviewing the quote (M8.14, M8.15). The renter accepts the rental terms in
 * force, which the booking records as a policy bundle (B-8).
 */
class SubmitBookingRequest extends FormRequest
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
            'accept_terms' => ['accepted'],
            // Consent under the Data Protection Act 2019 (M12.13).
            'accept_privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Please accept the rental terms to continue.',
            'accept_privacy.accepted' => 'Please agree to how we handle your details to continue.',
        ];
    }
}
