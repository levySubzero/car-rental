<?php

namespace App\Settings;

use App\Enums\MarqueePosition;
use Spatie\LaravelSettings\Settings;

class HomeSettings extends Settings
{
    public ?string $hero_media_uuid;

    public string $headline;

    public string $subline;

    public string $primary_cta_label;

    public string $secondary_cta_label;

    public int $featured_car_count;

    public MarqueePosition $marquee_position;

    // In display order
    /**
     * @phpstan-var list<array{key: string, visible: bool}>
     */
    public array $sections;

    /**
     * @phpstan-var list<array{value: string, label: string}>
     */
    public array $trust_figures;

    public static function group(): string
    {
        return 'home';
    }
}
