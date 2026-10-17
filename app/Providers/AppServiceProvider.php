<?php

namespace App\Providers;

use App\Enums\SettingsGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
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
