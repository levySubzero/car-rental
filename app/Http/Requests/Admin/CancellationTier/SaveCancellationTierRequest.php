<?php

namespace App\Http\Requests\Admin\CancellationTier;

use App\Models\CancellationTier;
use App\Support\Decimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a cancellation tier, or updates the one bound to the route.
 */
class SaveCancellationTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tier = $this->tier();

        return $tier === null
            ? ($this->user()?->can('create', CancellationTier::class) ?? false)
            : ($this->user()?->can('update', $tier) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hours_before_pickup' => ['required', 'integer', 'between:0,8760', Rule::unique('cancellation_tiers', 'hours_before_pickup')->ignore($this->tier())],
            'refund_percent' => ['required', 'string', 'regex:/^(100(\.0{1,2})?|\d{1,2}(\.\d{1,2})?)$/'],
            'label' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours_before_pickup.unique' => 'There is already a tier for this notice period.',
            'refund_percent.regex' => 'Enter a refund between 0 and 100%.',
        ];
    }

    /**
     * @return array{hours_before_pickup: int, refund_percent_bp: int, label: string|null}
     */
    public function tierData(): array
    {
        return [
            'hours_before_pickup' => (int) $this->validated('hours_before_pickup'),
            'refund_percent_bp' => Decimal::basisPoints((string) $this->validated('refund_percent')),
            'label' => $this->validated('label'),
        ];
    }

    private function tier(): ?CancellationTier
    {
        $tier = $this->route('cancellation_tier');

        return $tier instanceof CancellationTier ? $tier : null;
    }
}
