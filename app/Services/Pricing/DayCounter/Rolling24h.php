<?php

namespace App\Services\Pricing\DayCounter;

use Carbon\CarbonImmutable;

/**
 * Each started 24 hours counts, except a final part-day no longer than the grace period:
 * with 60 minutes' grace, 72h30 is 3 days and 73h01 is 4.
 */
final readonly class Rolling24h implements DayCounter
{
    public function __construct(private int $graceMinutes) {}

    public function count(CarbonImmutable $pickup, CarbonImmutable $return): int
    {
        $minutes = intdiv($return->getTimestamp() - $pickup->getTimestamp(), 60);
        $days = intdiv($minutes, 1_440) + ($minutes % 1_440 > $this->graceMinutes ? 1 : 0);

        return max(1, $days);
    }
}
