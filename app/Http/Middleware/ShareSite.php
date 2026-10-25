<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\CarType;
use App\Models\Media;
use App\Models\PaymentMethod;
use App\Services\Media\MediaUrlGenerator;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use App\Support\PublicCache;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares what every public page's chrome needs (M7.2–M7.4): contacts, socials, WhatsApp
 * templates, and the car types, brands and payment methods the header and footer list.
 * Applied to public routes only, as `site`.
 */
class ShareSite
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('site', function () {
            $settings = app(GeneralSettings::class);

            // Cleared when staff save a brand, car type, payment method or the home hero.
            $lists = PublicCache::remember(PublicCache::SITE, fn () => [
                'share_image' => $this->shareImage(),
                'car_types' => CarType::query()->active()->ordered()->get(['name', 'slug'])->toArray(),
                'brands' => Brand::query()->inMarquee()->get(['name', 'slug'])->toArray(),
                'payment_methods' => PaymentMethod::query()->active()->ordered()->pluck('name')->all(),
            ]);

            return [
                'company_name' => $settings->company_name,
                // Absolute, for canonical links, Open Graph and structured data (M7.17).
                'url' => rtrim((string) config('app.url'), '/'),
                'share_image' => $lists['share_image'],
                'latitude' => $settings->latitude,
                'longitude' => $settings->longitude,
                'phones' => $settings->phones,
                'whatsapp_number' => $settings->whatsapp_number,
                'email' => $settings->email,
                'address' => $settings->address,
                'map_embed_url' => $settings->map_embed_url,
                'socials' => $settings->socials,
                'whatsapp_templates' => $settings->whatsapp_templates,
                'car_types' => $lists['car_types'],
                'brands' => $lists['brands'],
                'payment_methods' => $lists['payment_methods'],
            ];
        });

        return $next($request);
    }

    /**
     * The home hero, absolute, as the default link-preview image.
     */
    private function shareImage(): ?string
    {
        $uuid = app(HomeSettings::class)->hero_media_uuid;
        $media = $uuid === null ? null : Media::query()->where('uuid', $uuid)->first();

        if ($media === null) {
            return null;
        }

        // Local disks give a root-relative URL; S3 or a CDN give an absolute one.
        $src = app(MediaUrlGenerator::class)->url($media, 'full');

        return str_starts_with($src, 'http') ? $src : rtrim((string) config('app.url'), '/').$src;
    }
}
