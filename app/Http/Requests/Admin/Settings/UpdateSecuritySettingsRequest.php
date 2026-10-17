<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SettingsGroup;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateSecuritySettingsRequest extends UpdateSettingsRequest
{
    public function group(): SettingsGroup
    {
        return SettingsGroup::Security;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'require_two_factor' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        return ['require_two_factor' => $this->boolean('require_two_factor')];
    }
}
