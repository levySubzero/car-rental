<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Long queue waits and failing supervisors (M12.10).
        if ($email = config('baines.ops_alert_email')) {
            Horizon::routeMailNotificationsTo($email);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user !== null
            && $user->canAccessAdmin()
            && $user->can(Permission::SystemHorizon));
    }
}
