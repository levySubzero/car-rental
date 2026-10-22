<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\MarqueePosition;
use App\Enums\SettingsGroup;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The home page's hero, layout and trust figures (M5.3). The section copy is the `home`
 * preset page (D22).
 */
class UpdateHomeSettingsRequest extends UpdateSettingsRequest
{
    // The home page sections M7.6 renders, in their default order.
    public const SECTIONS = ['about', 'featured_fleet', 'car_types', 'services', 'policy_highlights', 'requirements', 'why_choose_us', 'trust_strip', 'contact_cta'];

    public function group(): SettingsGroup
    {
        return SettingsGroup::Home;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hero_media_uuid' => ['nullable', 'uuid', new PendingOrOwnedMedia('site.hero')],
            'hero_video_uuid' => ['nullable', 'uuid', new PendingOrOwnedMedia('site.hero_video')],
            'headline' => ['required', 'string', 'max:120'],
            'subline' => ['required', 'string', 'max:300'],
            'primary_cta_label' => ['required', 'string', 'max:30'],
            'secondary_cta_label' => ['required', 'string', 'max:30'],
            'featured_car_count' => ['required', 'integer', 'between:1,12'],
            'marquee_position' => ['required', Rule::enum(MarqueePosition::class)],
            'sections' => ['required', 'array', 'size:'.count(self::SECTIONS)],
            'sections.*.key' => ['required', 'string', 'distinct', Rule::in(self::SECTIONS)],
            'sections.*.visible' => ['required', 'boolean'],
            'trust_figures' => ['present', 'array', 'max:6'],
            'trust_figures.*.value' => ['required', 'string', 'max:20'],
            'trust_figures.*.label' => ['required', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $data = $this->validated();

        $data['featured_car_count'] = (int) $data['featured_car_count'];
        $data['marquee_position'] = MarqueePosition::from($data['marquee_position']);
        $data['sections'] = array_map(
            fn (array $section) => ['key' => (string) $section['key'], 'visible' => (bool) $section['visible']],
            array_values($data['sections']),
        );
        $data['trust_figures'] = array_map(
            fn (array $figure) => ['value' => (string) $figure['value'], 'label' => (string) $figure['label']],
            array_values($data['trust_figures']),
        );

        return $data;
    }
}
