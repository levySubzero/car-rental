<?php

use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
| M12.2: every route that changes state without a signed-in user carries a rate limiter.
| Signed-in routes are covered by the staff guards (RouteGuardTest); Horizon has its own gate.
*/

it('throttles every public write route', function () {
    $unthrottled = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => array_diff($route->methods(), ['GET', 'HEAD']) !== [])
        ->reject(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'horizon/'))
        // Route::redirect answers every method but changes nothing.
        ->reject(fn (RoutingRoute $route) => ltrim((string) $route->getControllerClass(), '\\') === RedirectController::class)
        ->reject(function (RoutingRoute $route) {
            return collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:')));
        })
        ->reject(function (RoutingRoute $route) {
            return collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:'));
        })
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unthrottled)->toBe([]);
});
