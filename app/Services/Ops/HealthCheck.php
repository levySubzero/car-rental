<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What /health reports to the uptime monitor (M12.10): each check passes or fails, with no
 * detail beyond that. Works on both hosts (D44): the queue backlog is checked only for the
 * database queue the scheduler drains; Horizon raises its own alerts.
 */
class HealthCheck
{
    public const SCHEDULER_KEY = 'health:scheduler';

    // The heartbeat runs every minute; a few missed runs mean cron or schedule:work is down.
    private const SCHEDULER_STALE_SECONDS = 300;

    // A job waiting this long on the database queue means no worker is draining it.
    private const QUEUE_STALE_SECONDS = 900;

    /**
     * @return array{database: bool, cache: bool, scheduler: bool, queue: bool}
     */
    public function run(): array
    {
        return [
            'database' => $this->passes(fn () => DB::select('select 1') !== []),
            'cache' => $this->passes(function () {
                Cache::put('health:probe', $token = bin2hex(random_bytes(4)), 60);

                return Cache::get('health:probe') === $token;
            }),
            'scheduler' => $this->passes(fn () => now()->getTimestamp() - (int) Cache::get(self::SCHEDULER_KEY, 0) < self::SCHEDULER_STALE_SECONDS),
            'queue' => $this->passes(fn () => config('queue.default') !== 'database' || ! DB::table((string) config('queue.connections.database.table', 'jobs'))
                ->where('available_at', '<', now()->getTimestamp() - self::QUEUE_STALE_SECONDS)
                ->whereNull('reserved_at')
                ->exists()),
        ];
    }

    /**
     * @param  callable(): bool  $check
     */
    private function passes(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
