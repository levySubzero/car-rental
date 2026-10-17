<?php

namespace App\Enums;

enum MarqueePosition: string
{
    case BelowHero = 'below_hero';
    case PageBottom = 'page_bottom';

    public function label(): string
    {
        return match ($this) {
            self::BelowHero => 'Below the hero',
            self::PageBottom => 'Bottom of the page',
        };
    }
}
