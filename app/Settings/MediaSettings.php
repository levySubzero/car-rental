<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Admin limits per media collection. They can only tighten config/media.php,
 * which holds the hard ceilings (MediaCollectionRegistry).
 */
class MediaSettings extends Settings
{
    // Keyed by collection; a missing or null value uses the ceiling.
    /**
     * @phpstan-var array<string, array{max_files?: int|null, max_kb?: int|null, mimes?: list<string>|null}>
     */
    public array $collection_overrides;

    public static function group(): string
    {
        return 'media';
    }
}
