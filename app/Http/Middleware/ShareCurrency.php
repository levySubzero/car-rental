<?php

namespace App\Http\Middleware;

use App\Services\Pricing\DisplayCurrencyService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the renter's display currency and the rates to convert with (TECHNICAL_PLAN §14).
 * Applied to public routes only, as `currency`.
 */
class ShareCurrency
{
    public function __construct(private readonly DisplayCurrencyService $display) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('currency', function () use ($request) {
            $options = $this->display->options();

            return [
                'selected' => $this->display->selected($request->cookie(DisplayCurrencyService::COOKIE), array_column($options, 'code')),
                'options' => $options,
            ];
        });

        return $next($request);
    }
}
