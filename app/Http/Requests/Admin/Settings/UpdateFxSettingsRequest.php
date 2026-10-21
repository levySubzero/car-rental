<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\FxProvider;
use App\Enums\SettingsGroup;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

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
            // KES subtracted from the market rate for display prices, e.g. 3 or 2.50.
            'margin' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'stale_after_hours' => ['required', 'integer', 'between:1,168'],
            'max_jump_percent' => ['required', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['margin.regex' => 'Enter the margin in KES with at most 2 decimal places, e.g. 3.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        return [
            'provider' => (string) $this->validated('provider'),
            'refresh_minutes' => (int) $this->validated('refresh_minutes'),
            'margin_minor' => Money::fromMajor((string) $this->validated('margin'))->minor,
            'stale_after_hours' => (int) $this->validated('stale_after_hours'),
            'max_jump_percent' => (int) $this->validated('max_jump_percent'),
        ];
    }
}
