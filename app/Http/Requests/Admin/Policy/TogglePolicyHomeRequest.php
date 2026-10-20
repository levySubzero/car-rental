<?php

namespace App\Http\Requests\Admin\Policy;

use App\Models\Policy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TogglePolicyHomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Policy $policy */
        $policy = $this->route('policy');

        return $this->user()?->can('update', $policy) ?? false;
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
