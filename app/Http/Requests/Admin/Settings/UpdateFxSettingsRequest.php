<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\FxProvider;
use App\Enums\SettingsGroup;
use App\Services\Pricing\FxRateService;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFxSettingsRequest extends UpdateSettingsRequest
{
    public function group(): SettingsGroup
    {
        return SettingsGroup::Fx;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(FxProvider::class)],
            // The schedule runs every 15 minutes, so a shorter interval would not be honoured.
            'refresh_minutes' => ['required', 'integer', 'between:15,1440'],
            'margin_mode' => ['required', Rule::in(['below', 'above', 'exact'])],
            // KES below or above the market rate for display prices, e.g. 2 or 2.50.
            'margin_amount' => ['required_unless:margin_mode,exact', 'nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'stale_after_hours' => ['required', 'integer', 'between:1,168'],
            'max_jump_percent' => ['required', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['margin_amount.regex' => 'Enter the amount in KES with at most 2 decimal places, e.g. 2.'];
    }

    /**
     * A display rate of zero or less cannot convert anything, so that offset is refused.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $offset = Decimal::div((string) $this->marginMinor(), '100');

            foreach (app(FxRateService::class)->table()->rates as $rate) {
                if (! Decimal::isPositive(Decimal::sub($rate->market, $offset))) {
                    $validator->errors()->add('margin_amount', "This would make the {$rate->code} rate zero or negative.");

                    return;
                }
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        return [
            'provider' => (string) $this->validated('provider'),
            'refresh_minutes' => (int) $this->validated('refresh_minutes'),
            'margin_minor' => $this->marginMinor(),
            'stale_after_hours' => (int) $this->validated('stale_after_hours'),
            'max_jump_percent' => (int) $this->validated('max_jump_percent'),
        ];
    }

    /**
     * Stored signed: positive is below the market rate, negative above it, zero exact.
     */
    private function marginMinor(): int
    {
        $mode = (string) $this->input('margin_mode');

        if ($mode === 'exact') {
            return 0;
        }

        $amount = Money::fromMajor((string) $this->input('margin_amount'))->minor;

        return $mode === 'above' ? -$amount : $amount;
    }
}
