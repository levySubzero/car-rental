<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FxProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FxRate\RefreshFxRatesRequest;
use App\Http\Requests\Admin\FxRate\SetManualRateRequest;
use App\Http\Resources\Admin\FxRateResource;
use App\Models\FxRate;
use App\Services\Pricing\CurrencyService;
use App\Services\Pricing\FxRateService;
use App\Settings\FxSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rates in force, their history, manual rates and an on-demand refresh (M6.2).
 */
class FxRateController extends Controller
{
    public function __construct(
        private readonly FxRateService $fx,
        private readonly CurrencyService $currencies,
    ) {}

    public function index(FxSettings $settings): Response
    {
        Gate::authorize('viewAny', FxRate::class);

        $table = $this->fx->table();

        return Inertia::render('admin/fx-rates/index', [
            'base' => $this->currencies->baseCode(),
            'currencies' => $this->currencies->foreignCodes(),
            'rates' => array_values(array_map(fn ($rate) => $rate->toArray(), $table->rates)),
            'history' => FxRateResource::collection($this->fx->history()),
            'provider' => [
                'key' => $settings->provider,
                'label' => FxProvider::tryFrom($settings->provider)?->label() ?? $settings->provider,
                'has_key' => filled(config('services.fx.api_key')),
                'stale_after_hours' => $settings->stale_after_hours,
                'max_jump_percent' => $settings->max_jump_percent,
            ],
            'lastRefresh' => $this->fx->lastRefresh(),
        ]);
    }

    public function store(SetManualRateRequest $request): RedirectResponse
    {
        $code = (string) $request->validated('currency_code');
        $this->fx->setManualRate($code, (string) $request->validated('market_rate'), $request->until(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Manual {$code} rate saved. A fresh API rate still takes precedence."]);

        return to_route('admin.fx-rates.index');
    }

    public function expire(Request $request, FxRate $fxRate): RedirectResponse
    {
        Gate::authorize('update', $fxRate);

        $this->fx->expireManualRate($fxRate, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Manual {$fxRate->currency_code} rate ended."]);

        return to_route('admin.fx-rates.index');
    }

    public function refresh(RefreshFxRatesRequest $request): RedirectResponse
    {
        $result = $this->fx->refresh(force: true, acceptJumps: $request->boolean('accept_jumps'), actor: $request->user());

        Inertia::flash('toast', [
            'type' => $result->failed() ? 'error' : ($result->rejected !== [] ? 'warning' : 'success'),
            'message' => $result->message,
        ]);

        return to_route('admin.fx-rates.index');
    }
}
