<?php

namespace App\Http\Requests\Admin\Service;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleServiceActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Service $service */
        $service = $this->route('service');

        return $this->user()?->can('update', $service) ?? false;
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
