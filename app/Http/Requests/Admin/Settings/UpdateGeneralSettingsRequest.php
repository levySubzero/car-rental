<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SettingsGroup;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateGeneralSettingsRequest extends UpdateSettingsRequest
{
    public const SOCIAL_PLATFORMS = ['facebook', 'instagram', 'tiktok', 'x', 'youtube', 'linkedin'];

    public const WHATSAPP_TEMPLATES = ['car_enquiry', 'general_enquiry', 'booking'];

    public function group(): SettingsGroup
    {
        return SettingsGroup::General;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $phone = ['string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'];

        return [
            'company_name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'phones' => ['present', 'array', 'max:5'],
            'phones.*' => ['required', ...$phone],
            'whatsapp_number' => ['nullable', ...$phone],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'map_embed_url' => ['nullable', 'url:https', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'socials' => ['present', 'array:'.implode(',', self::SOCIAL_PLATFORMS)],
            'socials.*' => ['nullable', 'url:https', 'max:500'],
            'opening_hours' => ['present', 'array', 'max:10'],
            'opening_hours.*.days' => ['required', 'string', 'max:60'],
            'opening_hours.*.hours' => ['required', 'string', 'max:60'],
            'whatsapp_templates' => ['required', 'array:'.implode(',', self::WHATSAPP_TEMPLATES)],
            'whatsapp_templates.*' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Drop empty social links so only configured platforms are stored.
     *
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $data = $this->validated();
        $data['socials'] = array_filter($data['socials'], fn ($url) => filled($url));
        $data['latitude'] = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $data['longitude'] = isset($data['longitude']) ? (float) $data['longitude'] : null;

        return $data;
    }
}
