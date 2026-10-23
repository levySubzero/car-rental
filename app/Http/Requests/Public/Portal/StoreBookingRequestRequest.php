<?php

namespace App\Http\Requests\Public\Portal;

use App\Enums\BookingRequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A renter's cancellation or change request from the portal (M10B). The portal session
 * middleware has already confirmed who they are.
 */
class StoreBookingRequestRequest extends FormRequest
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
            'type' => ['required', Rule::enum(BookingRequestType::class)],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function type(): BookingRequestType
    {
        return BookingRequestType::from((string) $this->validated('type'));
    }
}
