<?php

namespace App\Services\Pricing\DayCounter;

use Carbon\CarbonImmutable;

/**
 * Each daytime window (07:00–19:00 by default) the rental overlaps counts as a day (PRD §5:
 * "one 12-hour daytime block"). Touching a window's edge does not count: a rental ending at
 * 07:00 does not use that day's window. A rental that is only at night is 1 day.
 */
final readonly class DaytimeBlocks implements DayCounter
{
    public function __construct(
        private string $timezone,
        private string $windowStart,
        private string $windowEnd,
    ) {}

    public function count(CarbonImmutable $pickup, CarbonImmutable $return): int
    {
        $day = $pickup->setTimezone($this->timezone)->startOfDay();
        $last = $return->setTimezone($this->timezone)->startOfDay();
        $blocks = 0;

        while ($day->lessThanOrEqualTo($last)) {
            $start = $day->setTimeFromTimeString($this->windowStart);
            $end = $day->setTimeFromTimeString($this->windowEnd);

            if ($start->lessThan($return) && $end->greaterThan($pickup)) {
                $blocks++;
            }

            $day = $day->addDay();
        }

        return max(1, $blocks);
    }
}
