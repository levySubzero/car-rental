<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveBookingToken;
use App\Http\Requests\Public\Booking\ExtendBookingRequest;
use App\Services\Booking\BookingExtensionService;
use App\Services\Booking\BookingTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The renter extending their hire from the booking page (D50): a live price for a new return,
 * the request itself, and a way into an extension's own page to pay for it.
 */
class BookingExtensionController extends Controller
{
    public function __construct(private readonly BookingExtensionService $extensions) {}

    public function preview(ExtendBookingRequest $request): JsonResponse
    {
        return response()->json($this->extensions->preview(ResolveBookingToken::booking($request), $request->returnAt()));
    }

    public function store(ExtendBookingRequest $request): RedirectResponse
    {
        $extension = $this->extensions->request(ResolveBookingToken::booking($request), $request->returnAt(), $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Extension requested ({$extension->reference}). We will email you once it has been reviewed."]);

        return back();
    }

    /**
     * Open one of this booking's extensions, to pay for it. Its own link is replaced, as when
     * the portal opens a booking (D39).
     */
    public function open(Request $request, string $token, string $reference, BookingTokenService $tokens): RedirectResponse
    {
        $extension = ResolveBookingToken::booking($request)->extensions()->where('reference', $reference)->firstOrFail();

        return to_route('bookings.show', $tokens->rotate($extension));
    }
}
