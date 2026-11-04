<?php

namespace App\Http\Middleware;

use App\Services\Integrations\IntegrationConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saved integration keys are in config before anything in the request reads it (M12B.15).
 */
class ApplyIntegrationConfig
{
    public function __construct(private readonly IntegrationConfig $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->config->apply();

        return $next($request);
    }
}
