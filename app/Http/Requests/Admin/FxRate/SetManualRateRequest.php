<?php

namespace App\Http\Requests\Admin\FxRate;

use App\Models\FxRate;
use App\Services\Pricing\CurrencyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetManualRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('override', FxRate::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', Rule::in(app(CurrencyService::class)->foreignCodes())],
            // KES per unit, up to 6 places, as quoted by banks.
            'market_rate' => ['required', 'string', 'regex:/^\d{1,8}(\.\d{1,6})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'override_until' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'market_rate.regex' => 'Enter KES per unit with at most 6 decimal places, e.g. 129.25.',
            'market_rate.not_regex' => 'The rate must be more than zero.',
        ];
    }

    public function until(): ?CarbonImmutable
    {
        $until = $this->validated('override_until');

        return $until === null ? null : CarbonImmutable::parse((string) $until);
    }
}
