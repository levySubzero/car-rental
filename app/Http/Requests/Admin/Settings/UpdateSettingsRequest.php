<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SettingsGroup;
use Illuminate\Foundation\Http\FormRequest;

abstract class UpdateSettingsRequest extends FormRequest
{
    abstract public function group(): SettingsGroup;

    public function authorize(): bool
    {
        return $this->user()?->can($this->group()->permission()) ?? false;
    }

    /**
     * The validated input, normalised to the settings property types.
     *
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        return $this->validated();
    }
}
