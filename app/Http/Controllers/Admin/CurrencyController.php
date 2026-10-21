<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Currency\ReorderCurrenciesRequest;
use App\Http\Requests\Admin\Currency\SaveCurrencyRequest;
use App\Http\Requests\Admin\Currency\ToggleCurrencyActiveRequest;
use App\Http\Resources\Admin\CurrencyResource;
use App\Models\Currency;
use App\Services\Pricing\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Display currencies. They are deactivated rather than deleted: bookings snapshot them.
 */
class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Currency::class);

        return Inertia::render('admin/currencies/index', [
            'currencies' => CurrencyResource::collection($this->currencies->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Currency::class);

        return Inertia::render('admin/currencies/form', ['currency' => null]);
    }

    public function store(SaveCurrencyRequest $request): RedirectResponse
    {
        /** @var array{code: string, name: string, symbol: string, decimals: int, is_active: bool} $data */
        $data = $request->currencyData();
        $currency = $this->currencies->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$currency->code} added. Set its exchange rate next."]);

        return to_route('admin.currencies.index');
    }

    public function edit(Currency $currency): Response
    {
        Gate::authorize('view', $currency);

        return Inertia::render('admin/currencies/form', [
            'currency' => CurrencyResource::make($currency),
        ]);
    }

    public function update(SaveCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        /** @var array{name: string, symbol: string, decimals: int, is_active: bool} $data */
        $data = $request->currencyData();
        $this->currencies->update($currency, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$currency->code} saved."]);

        return to_route('admin.currencies.index');
    }

    public function active(ToggleCurrencyActiveRequest $request, Currency $currency): RedirectResponse
    {
        $this->currencies->setActive($currency, $request->boolean('value'));

        return back();
    }

    public function reorder(ReorderCurrenciesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));
        $this->currencies->reorder($ids, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Currency order saved.']);

        return back();
    }
}
