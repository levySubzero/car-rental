<?php

namespace App\Http\Requests\Admin\Currency;

use App\Models\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a currency, or updates the one bound to the route. The code is fixed once created.
 */
class SaveCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currency = $this->currency();

        return $currency === null
            ? ($this->user()?->can('create', Currency::class) ?? false)
            : ($this->user()?->can('update', $currency) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...($this->currency() === null ? [
                'code' => ['required', 'string', 'regex:/^[A-Z]{3}$/', Rule::unique('currencies', 'code')],
            ] : []),
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['required', 'string', 'max:8'],
            'decimals' => ['required', 'integer', 'between:0,2'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{code?: string, name: string, symbol: string, decimals: int, is_active: bool}
     */
    public function currencyData(): array
    {
        /** @var array{code?: string, name: string, symbol: string, decimals: int|string, is_active: bool|string} $data */
        $data = $this->validated();

        return [...$data, 'decimals' => (int) $data['decimals'], 'is_active' => $this->boolean('is_active')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'Use the three-letter ISO 4217 code, e.g. USD.'];
    }

    private function currency(): ?Currency
    {
        $currency = $this->route('currency');

        return $currency instanceof Currency ? $currency : null;
    }
}
