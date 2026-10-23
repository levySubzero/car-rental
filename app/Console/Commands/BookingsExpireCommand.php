<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingService;
use App\Settings\PricingSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire')]
#[Description('Expire stale drafts and unpaid bookings, freeing their holds')]
class BookingsExpireCommand extends Command
{
    public function handle(BookingService $bookings, PricingSettings $pricing): int
    {
        $result = $bookings->expireStale($pricing->draft_expiry_hours);

        $this->components->twoColumnDetail('Drafts expired', (string) $result['drafts']);
        $this->components->twoColumnDetail('Unpaid bookings expired', (string) $result['unpaid']);

        return self::SUCCESS;
    }
}
