<?php

namespace App\Providers;

use App\Enums\SettingsGroup;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Services\Media\Contracts\TokenUploaderResolver;
use App\Services\Media\CurrentUploader;
use App\Services\Media\ImageDriver;
use App\Services\Media\MediaCollectionRegistry;
use App\Services\Media\NullTokenUploaderResolver;
use App\Settings\MediaSettings;
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

        // M8 binds the booking implementation.
        $this->app->bind(TokenUploaderResolver::class, NullTokenUploaderResolver::class);

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
