<?php

namespace App\Services\Settings;

use App\Enums\MediaStatus;
use App\Enums\SettingsGroup;
use App\Http\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaCollectionRegistry;
use App\Services\Media\MediaService;
use App\Settings\HomeSettings;
use App\Support\Media\MediaCollection;
use BackedEnum;
use Illuminate\Support\Facades\DB;
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
     * Extra read-only data a tab needs besides its values.
     *
     * @return array<string, mixed>
     */
    public function context(SettingsGroup $group): array
    {
        return match ($group) {
            // The uploads tab edits each collection within its configured ceiling.
            SettingsGroup::Media => [
                'collections' => array_values(array_map(fn (MediaCollection $ceiling) => [
                    ...$ceiling->toArray(),
                    'effective' => $this->registry()->get($ceiling->key)->toArray(),
                ], $this->registry()->ceilings())),
            ],
            // The hero uploader shows the current file and the collection's limits.
            SettingsGroup::Home => [
                'hero' => ($hero = $this->hero()) ? MediaResource::make($hero) : null,
                'heroLimits' => $this->registry()->get('site.hero')->toArray(),
            ],
            default => [],
        };
    }

    /**
     * Save a group and log the before and after values of what changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SettingsGroup $group, array $data, User $actor): Settings
    {
        $before = $this->values($group);

        $settings = DB::transaction(function () use ($group, $data, $actor) {
            // The hero is a standalone media file: attach the new one, release the old one.
            if ($group === SettingsGroup::Home && array_key_exists('hero_media_uuid', $data)) {
                $this->media()->claim('site.hero', array_filter([$data['hero_media_uuid']]), $actor);
            }

            return $this->for($group)->fill($data)->save();
        });

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

    private function hero(): ?Media
    {
        $uuid = app(HomeSettings::class)->hero_media_uuid;

        return $uuid === null ? null : Media::query()
            ->where('uuid', $uuid)
            ->where('collection', 'site.hero')
            ->where('status', MediaStatus::Attached)
            ->first();
    }

    private function media(): MediaService
    {
        return app(MediaService::class);
    }

    private function registry(): MediaCollectionRegistry
    {
        return app(MediaCollectionRegistry::class);
    }
}
