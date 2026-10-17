<?php

namespace App\Services\Settings;

use App\Enums\SettingsGroup;
use App\Models\User;
use BackedEnum;
use Spatie\LaravelSettings\Settings;

class SettingsService
{
    public function for(SettingsGroup $group): Settings
    {
        return app($group->settingsClass());
    }

    /**
     * Current values as plain data, with enums as their backing values.
     *
     * @return array<string, mixed>
     */
    public function values(SettingsGroup $group): array
    {
        return array_map(
            fn ($value) => $value instanceof BackedEnum ? $value->value : $value,
            $this->for($group)->toArray(),
        );
    }

    /**
     * Save a group and log the before and after values of what changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SettingsGroup $group, array $data, User $actor): Settings
    {
        $before = $this->values($group);

        $settings = $this->for($group)->fill($data)->save();

        $after = $this->values($group);
        $changed = array_keys(array_filter($after, fn ($value, $key) => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            activity('settings')
                ->causedBy($actor)
                ->withChanges([
                    'attributes' => array_intersect_key($after, array_flip($changed)),
                    'old' => array_intersect_key($before, array_flip($changed)),
                ])
                ->withProperties(['group' => $group->value])
                ->event('updated')
                ->log("{$group->label()} settings updated");
        }

        return $settings;
    }
}
