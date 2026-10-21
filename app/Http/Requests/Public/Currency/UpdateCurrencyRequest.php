<?php

namespace App\Http\Requests\Public\Currency;

use App\Services\Pricing\DisplayCurrencyService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', Rule::in(app(DisplayCurrencyService::class)->codes())],
        ];
    }
}
