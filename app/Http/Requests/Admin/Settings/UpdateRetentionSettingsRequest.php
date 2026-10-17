<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SettingsGroup;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateRetentionSettingsRequest extends UpdateSettingsRequest
{
    public function group(): SettingsGroup
    {
        return SettingsGroup::Retention;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kyc_retention_months' => ['required', 'integer', 'between:1,120'],
            'draft_retention_days' => ['required', 'integer', 'between:1,90'],
            'activity_log_retention_months' => ['required', 'integer', 'between:1,120'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        return array_map('intval', $this->validated());
    }
}
