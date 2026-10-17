<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route guard (TECHNICAL_PLAN §5)
|--------------------------------------------------------------------------
|
| Every admin route must refuse a staff member who holds no permissions.
| A new route without a policy, FormRequest::authorize() or can: check
| fails here. Routes every staff member may open are listed explicitly.
|
*/

const OPEN_TO_ALL_STAFF = [
    'admin.dashboard',
];

/**
 * Sample values for route parameters. Add one when a new binding appears.
 *
 * @return array<string, mixed>
 */
function sampleParameters(RoutingRoute $route): array
{
    $samples = [
        'user' => fn () => User::factory()->staff()->create()->id,
        'role' => fn () => Role::findOrCreate('sample-role', 'web')->id,
        'group' => fn () => 'general',
    ];

    return collect($route->parameterNames())
        ->mapWithKeys(function (string $name) use ($samples, $route) {
            if (! isset($samples[$name])) {
                throw new RuntimeException("Add a sample value for {{$name}} on route [{$route->getName()}] in RouteGuardTest.");
            }

            return [$name => $samples[$name]()];
        })
        ->all();
}

it('refuses staff without permissions on every admin route', function () {
    $user = staffWith();

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with((string) $route->getName(), 'admin.'))
        ->reject(fn (RoutingRoute $route) => in_array($route->getName(), OPEN_TO_ALL_STAFF, true));

    expect($routes)->not->toBeEmpty();

    $unguarded = [];

    foreach ($routes as $route) {
        $method = collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->first();
        $url = route($route->getName(), sampleParameters($route));

        $status = $this->actingAs($user)->call($method, $url)->getStatusCode();

        if ($status !== 403) {
            $unguarded[] = "{$method} {$route->getName()} → {$status}";
        }
    }

    expect($unguarded)->toBe([]);
});

it('lets every staff member open the routes meant for all staff', function () {
    $user = staffWith();

    foreach (OPEN_TO_ALL_STAFF as $name) {
        $this->actingAs($user)->get(route($name))->assertOk();
    }
});
