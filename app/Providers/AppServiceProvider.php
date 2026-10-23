<?php

namespace App\Providers;

use App\Enums\FxProvider;
use App\Enums\SettingsGroup;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Brand;
use App\Models\CancellationTier;
use App\Models\Car;
use App\Models\CarType;
use App\Models\Charge;
use App\Models\ContactMessage;
use App\Models\Currency;
use App\Models\DeliveryZone;
use App\Models\DepositBand;
use App\Models\FxRate;
use App\Models\Media;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\PolicyBundle;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\BookingTokenResolver;
use App\Services\Media\Contracts\TokenUploaderResolver;
use App\Services\Media\CurrentUploader;
use App\Services\Media\ImageDriver;
use App\Services\Media\MediaCollectionRegistry;
use App\Services\Pricing\Fx\FxRateProvider;
use App\Services\Pricing\Fx\ManualProvider;
use App\Services\Pricing\Fx\OpenExchangeRatesProvider;
use App\Settings\FxSettings;
use App\Settings\MediaSettings;
use App\Support\Content\PageRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageDriver::class);
        $this->app->scoped(CurrentUploader::class);
        $this->app->singleton(PageRegistry::class, fn () => PageRegistry::fromConfig());

        // Renters upload KYC documents with their booking token (M8.12).
        $this->app->bind(TokenUploaderResolver::class, BookingTokenResolver::class);

        // Chosen per resolution: admins can switch the provider in the FX settings tab.
        $this->app->bind(FxRateProvider::class, fn ($app) => match (FxProvider::tryFrom($app->make(FxSettings::class)->provider)) {
            FxProvider::OpenExchangeRates => new OpenExchangeRatesProvider(config('services.fx.api_key')),
            default => new ManualProvider,
        });

        // Not a singleton: admins can change the overrides mid-request (settings save).
        $this->app->bind(MediaCollectionRegistry::class, fn ($app) => new MediaCollectionRegistry(
            definitions: (array) config('media.collections'),
            overrides: $app->make(MediaSettings::class)->collection_overrides,
            unsupportedExtensions: $app->make(ImageDriver::class)->supports('heic') ? [] : ['heic'],
            kbCeiling: (int) config('baines.uploads.max_kb_ceiling'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Polymorphic columns store these aliases, never class names (D17). Media collections name
     * their owner by alias, and the uploader sends it as owner_type. Each module adds its models.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'media' => Media::class,
            'brand' => Brand::class,
            'car_type' => CarType::class,
            'car' => Car::class,
            'availability_block' => AvailabilityBlock::class,
            'service' => Service::class,
            'page' => Page::class,
            'policy' => Policy::class,
            'policy_bundle' => PolicyBundle::class,
            'payment_method' => PaymentMethod::class,
            'contact_message' => ContactMessage::class,
            'currency' => Currency::class,
            'fx_rate' => FxRate::class,
            'charge' => Charge::class,
            'delivery_zone' => DeliveryZone::class,
            'deposit_band' => DepositBand::class,
            'cancellation_tier' => CancellationTier::class,
            'booking' => Booking::class,
        ]);
    }

    /**
     * Named limiters for public endpoints (TECHNICAL_PLAN §15). Use them with throttle:{name}.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('booking-start', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('booking-step', fn (Request $request) => Limit::perMinute(30)->by(
            $request->route('token') ?? $request->ip(),
        ));

        RateLimiter::for('media-upload', fn (Request $request) => $request->user()
            ? Limit::perMinute(60)->by('user:'.$request->user()->getAuthIdentifier())
            : Limit::perHour(30)->by('token:'.($request->header('X-Booking-Token') ?? $request->ip())));

        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        // The booking form re-prices as the renter changes dates and extras.
        RateLimiter::for('quote-preview', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }

    /**
     * Super admins pass every ability check. Everyone else is checked against permissions only.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        // Used as can:update-settings,group on the admin settings routes.
        Gate::define('update-settings', fn (User $user, SettingsGroup $group): bool => $user->can($group->permission()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Single resources are Inertia props as-is; paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
