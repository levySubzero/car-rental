<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Currency\UpdateCurrencyRequest;
use App\Services\Pricing\DisplayCurrencyService;
use Illuminate\Http\RedirectResponse;

/**
 * The currency switcher (M6.13). Display only: renters always pay in KES.
 */
class CurrencyController extends Controller
{
    public function update(UpdateCurrencyRequest $request): RedirectResponse
    {
        return back()->withCookie(cookie(DisplayCurrencyService::COOKIE, (string) $request->validated('currency'), 60 * 24 * 365));
    }
}
