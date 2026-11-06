<?php

namespace App\Http\Requests\Admin\Booking;

use App\Enums\VoucherBucket;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAnyVoucher', Booking::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bucket' => ['nullable', Rule::enum(VoucherBucket::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'car' => ['nullable', 'integer'],
        ];
    }
}
