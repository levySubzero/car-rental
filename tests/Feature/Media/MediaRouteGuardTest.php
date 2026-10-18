<?php

use App\Services\Media\MediaService;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\Gadget;
use Tests\Support\MediaFixtures;

/*
| RouteGuardTest only walks admin.* routes. The media routes have no auth middleware
| (renters use tokens), so every one is checked here against a private, attached file:
| a guest and a staff member without permissions must both be refused.
*/

beforeEach(function () {
    MediaFixtures::setUp();
    $gadget = Gadget::create(['name' => 'Guarded']);
    $service = app(MediaService::class);

    $this->doc = $service->upload(MediaFixtures::pdf(), 'gadget.docs', staffWith());
    $service->sync($gadget, 'gadget.docs', [$this->doc->uuid]);

    $this->payloads = [
        'media.store' => ['collection' => 'gadget.docs', 'file' => MediaFixtures::pdf()],
        'media.reorder' => ['collection' => 'gadget.docs', 'owner_type' => 'gadget', 'owner_id' => $gadget->id, 'uuids' => [$this->doc->uuid]],
        'media.update' => ['alt' => 'x'],
    ];
});

function mediaRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'media.'))
        ->values()
        ->all();
}

it('covers every media route', function () {
    expect(collect(mediaRoutes())->map->getName()->sort()->values()->all())->toBe([
        'media.destroy', 'media.download', 'media.reorder', 'media.show', 'media.signed', 'media.store', 'media.update',
    ]);
});

it('refuses guests and staff without permissions on every media route', function (?bool $asStaff) {
    if ($asStaff) {
        $this->actingAs(staffWith());
    }

    foreach (mediaRoutes() as $route) {
        $name = (string) $route->getName();
        $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
        $url = route($name, in_array('media', $route->parameterNames(), true) ? ['media' => $this->doc->uuid] : []);

        $response = $this->withHeaders(['Accept' => 'application/json'])->call($method, $url, $this->payloads[$name] ?? []);

        expect($response->status())->toBe(403, "{$method} {$name} returned {$response->status()}");
    }
})->with(['guest' => [false], 'staff without permissions' => [true]]);
