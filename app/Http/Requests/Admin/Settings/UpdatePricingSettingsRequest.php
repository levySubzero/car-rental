<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\DayCountingStrategy;
use App\Enums\RoundingMode;
use App\Enums\SettingsGroup;
use App\Support\Decimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Quote inputs (M6.14). The base currency is fixed (KES) and not editable here.
 */
class UpdatePricingSettingsRequest extends UpdateSettingsRequest
{
    public function group(): SettingsGroup
    {
        return SettingsGroup::Pricing;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Typed as a percent ("25"), stored in basis points.
            'reservation_percent' => ['required', 'string', 'regex:/^(100(\.0{1,2})?|\d{1,2}(\.\d{1,2})?)$/', 'not_regex:/^0+(\.0+)?$/'],
            'reservation_rounding' => ['required', Rule::enum(RoundingMode::class)],
            'day_counting' => ['required', Rule::enum(DayCountingStrategy::class)],
            'daytime_window_start' => ['required', 'date_format:H:i'],
            'daytime_window_end' => ['required', 'date_format:H:i', 'after:daytime_window_start'],
            'rolling_grace_minutes' => ['required', 'integer', 'between:0,720'],
            'hold_minutes' => ['required', 'integer', 'between:5,1440'],
            'cash_hold_hours' => ['required', 'integer', 'between:1,168'],
            'draft_expiry_hours' => ['required', 'integer', 'between:1,168'],
            'turnaround_minutes' => ['required', 'integer', 'between:0,1440'],
            'default_mileage_km' => ['required', 'integer', 'between:1,10000'],
            'unlimited_mileage_after_days' => ['nullable', 'integer', 'between:1,365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reservation_percent.regex' => 'Enter a percentage between 0 and 100, e.g. 25.',
            'reservation_percent.not_regex' => 'The reservation must be more than 0%.',
            'daytime_window_end.after' => 'The window must end after it starts.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $unlimited = $this->validated('unlimited_mileage_after_days');

        return [
            'reservation_percent_bp' => Decimal::basisPoints((string) $this->validated('reservation_percent')),
            'reservation_rounding' => RoundingMode::from((string) $this->validated('reservation_rounding')),
            'day_counting' => DayCountingStrategy::from((string) $this->validated('day_counting')),
            'daytime_window_start' => (string) $this->validated('daytime_window_start'),
            'daytime_window_end' => (string) $this->validated('daytime_window_end'),
            'rolling_grace_minutes' => (int) $this->validated('rolling_grace_minutes'),
            'hold_minutes' => (int) $this->validated('hold_minutes'),
            'cash_hold_hours' => (int) $this->validated('cash_hold_hours'),
            'draft_expiry_hours' => (int) $this->validated('draft_expiry_hours'),
            'turnaround_minutes' => (int) $this->validated('turnaround_minutes'),
            'default_mileage_km' => (int) $this->validated('default_mileage_km'),
            'unlimited_mileage_after_days' => $unlimited === null ? null : (int) $unlimited,
        ];
    }
}
