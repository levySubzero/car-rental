<?php

namespace App\Support;

use App\Models\Media;
use App\Services\Media\MediaUrlGenerator;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;

/**
 * What resources/views/app.blade.php prints for link previews when SSR is off. Pages without
 * their own image fall back to the home hero, as the React Seo component does.
 */
final class SharePreview
{
    /**
     * @return array{title: string, description: string|null, url: string, site_name: string, image: string|null}
     */
    public static function for(string $title, ?string $description, ?Media $image = null): array
    {
        $company = app(GeneralSettings::class)->company_name;
        $hero = app(HomeSettings::class)->hero_media_uuid;
        $image ??= $hero === null ? null : Media::query()->where('uuid', $hero)->first();

        return [
            'title' => "{$title} - {$company}",
            'description' => $description,
            'url' => rtrim((string) config('app.url'), '/').'/'.ltrim(request()->path(), '/'),
            'site_name' => $company,
            'image' => $image === null ? null : app(MediaUrlGenerator::class)->shareUrl($image),
        ];
    }
}
