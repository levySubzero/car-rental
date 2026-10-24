<?php

namespace App\Http\Requests\Admin\Booking;

use App\Enums\BookingBucket;
use App\Enums\PaymentMethodCode;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Booking::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bucket' => ['nullable', Rule::enum(BookingBucket::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'car' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(PaymentMethodCode::class)],
        ];
    }
}
