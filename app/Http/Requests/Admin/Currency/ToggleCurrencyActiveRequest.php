<?php

namespace App\Http\Requests\Admin\Currency;

use App\Models\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleCurrencyActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currency = $this->route('currency');

        return $currency instanceof Currency && ($this->user()?->can('update', $currency) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['value' => ['required', 'boolean']];
    }
}
