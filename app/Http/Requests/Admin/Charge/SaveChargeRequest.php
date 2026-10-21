<?php

namespace App\Http\Requests\Admin\Charge;

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a charge, or updates the one bound to the route. The amount is typed in major
 * units of the charge's currency. Only info-only and per-km charges may leave it empty. The
 * code is fixed once created: renters' picks and bookings refer to it.
 */
class SaveChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $charge = $this->charge();

        return $charge === null
            ? ($this->user()?->can('create', Charge::class) ?? false)
            : ($this->user()?->can('update', $charge) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...($this->charge() === null ? [
                'code' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('charges', 'code')],
            ] : []),
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'basis' => ['required', Rule::enum(ChargeBasis::class)],
            'amount' => [
                Rule::requiredIf(fn () => ChargeBasis::tryFrom((string) $this->input('basis'))?->isQuotable() ?? false),
                'nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/',
            ],
            'currency_code' => ['required', 'string', Rule::exists('currencies', 'code')->where('is_active', true)],
            'tax_inclusive' => ['required', 'boolean'],
            'payable_at' => ['required', Rule::enum(ChargePayableAt::class)],
            'applies' => ['required', Rule::enum(ChargeApplies::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use lowercase letters, numbers and underscores, starting with a letter.',
            'amount.required' => 'Per-day and per-booking charges need an amount.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chargeData(): array
    {
        $data = $this->safe()->except('amount');
        $amount = $this->validated('amount');

        return [
            ...$data,
            'amount_minor' => filled($amount) ? Money::fromMajor((string) $amount)->minor : null,
            'tax_inclusive' => $this->boolean('tax_inclusive'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function charge(): ?Charge
    {
        $charge = $this->route('charge');

        return $charge instanceof Charge ? $charge : null;
    }
}
