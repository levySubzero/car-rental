<?php

namespace App\Http\Requests\Admin\Availability;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The availability calendar (M11.9): for the bookings desk and whoever manages staff blocks.
 */
class IndexAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can(Permission::BookingsView) || $user->can(Permission::AvailabilityManage));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'in:14,28'],
            'car' => ['nullable', 'integer'],
        ];
    }
}
