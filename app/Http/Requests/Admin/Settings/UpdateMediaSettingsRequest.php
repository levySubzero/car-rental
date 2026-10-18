<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SettingsGroup;
use App\Services\Media\MediaCollectionRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Per-collection upload limits. Each value must stay within the collection's
 * config/media.php ceiling; a value equal to the ceiling is stored as "no override".
 */
class UpdateMediaSettingsRequest extends UpdateSettingsRequest
{
    public function group(): SettingsGroup
    {
        return SettingsGroup::Media;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['collections' => ['present', 'array']];

        foreach ($this->registry()->ceilings() as $key => $ceiling) {
            $field = 'collections.'.str_replace('.', '\.', $key);

            $rules[$field] = ['required', 'array'];
            $rules["{$field}.max_files"] = ['required', 'integer', 'between:1,'.$ceiling->maxFiles];
            $rules["{$field}.max_kb"] = ['required', 'integer', 'between:1,'.$ceiling->maxKb];
            $rules["{$field}.mimes"] = ['required', 'array', 'min:1'];
            $rules["{$field}.mimes.*"] = ['string', Rule::in($ceiling->mimes)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->registry()->ceilings() as $key => $ceiling) {
            $field = 'collections.'.str_replace('.', '\.', $key);
            $attributes["{$field}.max_files"] = "{$ceiling->label} file limit";
            $attributes["{$field}.max_kb"] = "{$ceiling->label} size limit";
            $attributes["{$field}.mimes"] = "{$ceiling->label} file types";
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        /** @var array<string, array{max_files: int|string, max_kb: int|string, mimes: list<string>}> $input */
        $input = $this->validated('collections', []);
        $overrides = [];

        foreach ($this->registry()->ceilings() as $key => $ceiling) {
            if (! isset($input[$key])) {
                continue;
            }

            $maxFiles = (int) $input[$key]['max_files'];
            $maxKb = (int) $input[$key]['max_kb'];
            // Keep the configured order so "all types" compares equal to the ceiling.
            $mimes = array_values(array_intersect($ceiling->mimes, $input[$key]['mimes']));

            $override = array_filter([
                'max_files' => $maxFiles < $ceiling->maxFiles ? $maxFiles : null,
                'max_kb' => $maxKb < $ceiling->maxKb ? $maxKb : null,
                'mimes' => $mimes !== $ceiling->mimes ? $mimes : null,
            ], fn ($value) => $value !== null);

            if ($override !== []) {
                $overrides[$key] = $override;
            }
        }

        return ['collection_overrides' => $overrides];
    }

    private function registry(): MediaCollectionRegistry
    {
        return app(MediaCollectionRegistry::class);
    }
}
