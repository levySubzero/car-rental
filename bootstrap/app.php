<?php

use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveMediaUploader;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ShareCurrency;
use App\Http\Middleware\ShareSite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['web', 'auth', 'admin.idle', 'verified', 'staff', '2fa'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'media.uploader'])
                ->group(base_path('routes/media.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'staff' => EnsureStaff::class,
            '2fa' => EnsureTwoFactor::class,
            'admin.idle' => AdminIdleTimeout::class,
            'media.uploader' => ResolveMediaUploader::class,
            'currency' => ShareCurrency::class,
            'site' => ShareSite::class,
        ]);

        $middleware->web(append: [
            EnsureActiveAccount::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
