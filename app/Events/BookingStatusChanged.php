<?php

namespace App\Events;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after every booking status change is committed. Payments (M9) and notifications
 * (M10) listen to it.
 */
class BookingStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Booking $booking,
        public readonly BookingStatus $from,
        public readonly BookingStatus $to,
    ) {}
}
