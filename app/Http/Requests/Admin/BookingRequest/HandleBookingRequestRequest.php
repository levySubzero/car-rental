<?php

namespace App\Http\Requests\Admin\BookingRequest;

use App\Models\BookingRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HandleBookingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BookingRequest $bookingRequest */
        $bookingRequest = $this->route('booking_request');

        return $this->user()?->can('update', $bookingRequest) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
