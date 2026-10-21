<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Quote\QuotePreviewRequest;
use App\Http\Resources\Public\QuoteResource;
use App\Services\Pricing\QuoteService;

/**
 * Live price for the booking form (M6.17, D31): the server is the only quote engine.
 * Errors such as an age outside every deposit band come back as JSON 422 on their field.
 */
class QuotePreviewController extends Controller
{
    public function __invoke(QuotePreviewRequest $request, QuoteService $quotes): QuoteResource
    {
        return QuoteResource::make($quotes->quote(
            $request->car(),
            $request->pickup(),
            $request->returnAt(),
            $request->extras(),
            $request->zoneId(),
            $request->driverDob(),
        ));
    }
}
