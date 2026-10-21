<?php

namespace App\Http\Requests\Admin\DepositBand;

use App\Models\DepositBand;
use App\Support\Decimal;
use App\Support\Rules\NonOverlappingAgeBand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creates a deposit band, or updates the one bound to the route. The percentage of the car
 * value is typed as a percent ("2" or "2.5") and stored in basis points.
 */
class SaveDepositBandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $band = $this->band();

        return $band === null
            ? ($this->user()?->can('create', DepositBand::class) ?? false)
            : ($this->user()?->can('update', $band) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_age' => ['required', 'integer', 'between:16,99', new NonOverlappingAgeBand($this->band())],
            'max_age' => ['required', 'integer', 'between:16,99', 'gte:min_age'],
            'percent' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'label' => ['nullable', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['percent.regex' => 'Enter a percentage of the car value below 100, e.g. 2 or 2.5.'];
    }

    /**
     * @return array{min_age: int, max_age: int, percent_bp: int, label: string|null, is_active: bool}
     */
    public function bandData(): array
    {
        return [
            'min_age' => (int) $this->validated('min_age'),
            'max_age' => (int) $this->validated('max_age'),
            'percent_bp' => Decimal::basisPoints((string) $this->validated('percent')),
            'label' => $this->validated('label'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function band(): ?DepositBand
    {
        $band = $this->route('deposit_band');

        return $band instanceof DepositBand ? $band : null;
    }
}
