<?php

namespace App\Services\Pricing\DayCounter;

use Carbon\CarbonImmutable;

/**
 * Turns a rental period into billable days (PricingSettings::day_counting, PRD Q3).
 * Implementations work in the business time zone (D30), return at least 1, and expect
 * the return to be after the pickup.
 */
interface DayCounter
{
    public function count(CarbonImmutable $pickup, CarbonImmutable $return): int;
}
