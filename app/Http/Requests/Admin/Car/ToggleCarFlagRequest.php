<?php

namespace App\Http\Requests\Admin\Car;

use App\Models\Car;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Availability and featured switches in the car list.
 */
class ToggleCarFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Car $car */
        $car = $this->route('car');

        return $this->user()?->can('update', $car) ?? false;
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
