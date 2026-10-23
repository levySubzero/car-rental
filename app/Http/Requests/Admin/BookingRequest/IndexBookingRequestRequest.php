<?php

namespace App\Http\Requests\Admin\BookingRequest;

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBookingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', BookingRequest::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(BookingRequestStatus::class)],
        ];
    }
}
