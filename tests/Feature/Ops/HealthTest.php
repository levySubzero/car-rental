<?php

use App\Services\Ops\HealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
| M12.10: /health for the uptime monitor.
*/

beforeEach(fn () => Cache::put(HealthCheck::SCHEDULER_KEY, now()->getTimestamp()));

it('is ok when the database, cache, scheduler and queue are', function () {
    $this->getJson(route('health'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => true, 'cache' => true, 'scheduler' => true, 'queue' => true]])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('fails when the scheduler has stopped', function () {
    $this->travel(6)->minutes();

    $this->getJson(route('health'))->assertStatus(503)->assertJsonPath('checks.scheduler', false);
});

it('fails when the database queue is not being drained', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(20)->getTimestamp(), 'created_at' => now()->subMinutes(20)->getTimestamp()]);

    $this->getJson(route('health'))->assertStatus(503)->assertJsonPath('checks.queue', false);
});

it('sets no session cookie', function () {
    expect($this->getJson(route('health'))->headers->getCookies())->toBe([]);
});
