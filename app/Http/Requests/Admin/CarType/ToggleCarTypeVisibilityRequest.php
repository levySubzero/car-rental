<?php

namespace App\Http\Requests\Admin\CarType;

use App\Models\CarType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The "Shown on the website" switch in the car type list.
 */
class ToggleCarTypeVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CarType $carType */
        $carType = $this->route('car_type');

        return $this->user()?->can('update', $carType) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'boolean'],
        ];
    }
}
