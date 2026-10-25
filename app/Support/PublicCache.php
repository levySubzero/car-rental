<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\CarType;
use App\Models\Currency;
use App\Models\FxRate;
use App\Models\PaymentMethod;
use App\Settings\FxSettings;
use App\Settings\HomeSettings;
use App\Settings\PricingSettings;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelSettings\Events\SettingsSaved;

/**
 * What every public page shares, cached between requests (M12.7): the header and footer
 * lists (`site`) and the display currencies with their rates (`currency`). Admin saves clear
 * the key at once; the TTL is a backstop, and covers rates going stale with time.
 */
final class PublicCache
{
    public const SITE = 'public:site-chrome';

    public const CURRENCIES = 'public:currencies';

    public const TTL_SECONDS = 300;

    /**
     * Models and settings whose saves change each key.
     */
    private const SOURCES = [
        self::SITE => [Brand::class, CarType::class, PaymentMethod::class, HomeSettings::class],
        self::CURRENCIES => [Currency::class, FxRate::class, FxSettings::class, PricingSettings::class],
    ];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public static function register(): void
    {
        foreach (self::SOURCES as $key => $sources) {
            foreach ($sources as $source) {
                if (is_subclass_of($source, Model::class)) {
                    $source::saved(fn () => Cache::forget($key));
                    $source::deleted(fn () => Cache::forget($key));
                }
            }
        }

        Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
            foreach (self::SOURCES as $key => $sources) {
                if (in_array($event->settings::class, $sources, true)) {
                    Cache::forget($key);
                }
            }
        });
    }
}
