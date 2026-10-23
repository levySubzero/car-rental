<?php

use App\Models\AvailabilityBlock;
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
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\Role;
use App\Models\Service;
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
        'brand' => fn () => Brand::factory()->create()->slug,
        'car_type' => fn () => CarType::factory()->create()->slug,
        'car' => fn () => Car::factory()->create()->slug,
        // Scoped to the {car} sampled before it.
        'blackout' => fn (array $resolved) => AvailabilityBlock::factory()
            ->for(Car::query()->where('slug', $resolved['car'])->sole())
            ->create()->id,
        'service' => fn () => Service::factory()->create()->slug,
        // Preset slugs and payment codes are unique, so reuse the row an earlier route created.
        'page' => fn () => Page::query()->firstOrCreate(['slug' => 'about'], ['content' => []])->slug,
        'policy' => fn () => Policy::factory()->published()->withDraft()->create()->slug,
        'payment_method' => fn () => (PaymentMethod::query()->firstWhere('code', 'mpesa') ?? PaymentMethod::factory()->create())->code->value,
        'contact_message' => fn () => ContactMessage::factory()->create()->id,
        // Currency codes are unique, so reuse USD once an earlier route created it.
        'currency' => fn () => (Currency::query()->firstWhere('code', 'USD') ?? Currency::factory()->code('USD')->create())->code,
        'charge' => function () {
            Currency::query()->firstWhere('code', 'KES') ?? Currency::factory()->code('KES')->create();

            return Charge::factory()->create()->code;
        },
        'delivery_zone' => fn () => DeliveryZone::factory()->create()->id,
        'deposit_band' => fn () => DepositBand::factory()->create()->id,
        'cancellation_tier' => fn () => CancellationTier::factory()->create()->id,
        'fx_rate' => function () {
            Currency::query()->firstWhere('code', 'USD') ?? Currency::factory()->code('USD')->create();

            return FxRate::factory()->manual()->create()->id;
        },
    ];

    $resolved = [];

    foreach ($route->parameterNames() as $name) {
        if (! isset($samples[$name])) {
            throw new RuntimeException("Add a sample value for {{$name}} on route [{$route->getName()}] in RouteGuardTest.");
        }

        // Each sample may use the parameters resolved before it (nested routes).
        $resolved[$name] = $samples[$name]($resolved);
    }

    return $resolved;
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
