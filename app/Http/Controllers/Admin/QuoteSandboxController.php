<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Domain\MissingFxRateException;
use App\Exceptions\Domain\QuoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Quote\QuoteSandboxRequest;
use App\Http\Resources\Admin\QuoteResource;
use App\Services\Pricing\QuoteService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Price any car, dates and extras with the live rules (M6.15). The query string holds the
 * scenario, so a link reproduces it: Gate G3 checks the client's spreadsheet cases here.
 */
class QuoteSandboxController extends Controller
{
    public function __invoke(QuoteSandboxRequest $request, QuoteService $quotes): Response
    {
        $quote = $error = null;

        if ($request->isComplete()) {
            try {
                $quote = QuoteResource::make($quotes->quote(
                    $request->car(),
                    $request->pickup(),
                    $request->returnAt(),
                    $request->extras(),
                    $request->zoneId(),
                    $request->driverDob(),
                ));
            } catch (QuoteException|MissingFxRateException $exception) {
                $error = $exception->getMessage();
            }
        }

        return Inertia::render('admin/quote-sandbox/index', [
            'values' => $request->formValues(),
            'quote' => $quote,
            'error' => $error,
            'timezone' => config('baines.timezone'),
            ...$quotes->sandboxOptions(),
        ]);
    }
}
