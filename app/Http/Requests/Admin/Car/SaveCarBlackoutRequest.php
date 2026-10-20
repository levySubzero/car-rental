<?php

namespace App\Http\Requests\Admin\Car;

use App\Enums\BlackoutReason;
use App\Models\Car;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCarBlackoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Car $car */
        $car = $this->route('car');

        return $this->user()?->can('manageAvailability', $car) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', Rule::enum(BlackoutReason::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
