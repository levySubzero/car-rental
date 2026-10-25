<?php

namespace App\Http\Controllers;

use App\Services\Ops\HealthCheck;
use Illuminate\Http\JsonResponse;

/**
 * For the uptime monitor (M12.10): 200 when every check passes, 503 otherwise. Laravel's
 * /up only says the app boots.
 */
class HealthController extends Controller
{
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $checks = $health->run();
        $ok = ! in_array(false, $checks, true);

        return response()
            ->json(['status' => $ok ? 'ok' : 'fail', 'checks' => $checks], $ok ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
