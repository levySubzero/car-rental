<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Services\Booking\BookingTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the `{token}` in a renter's booking URL into the booking, or a 404 (M8.5). The token
 * stays a plain route parameter, so the `booking-step` limiter can key on it. Applied as
 * `booking.token`.
 */
class ResolveBookingToken
{
    public const ATTRIBUTE = 'booking';

    public function __construct(private readonly BookingTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $booking = $this->tokens->find((string) $request->route('token'));

        abort_if($booking === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $booking);

        return $next($request);
    }

    /**
     * The booking this request's token resolved to.
     */
    public static function booking(Request $request): Booking
    {
        $booking = $request->attributes->get(self::ATTRIBUTE);
        abort_unless($booking instanceof Booking, 404);

        return $booking;
    }
}
