<?php

namespace App\Services\Pricing\DayCounter;

use Carbon\CarbonImmutable;

/**
 * Every calendar date from pickup to return counts, both included (the client's rule):
 * Monday 10:00 to Wednesday 09:00 is 3 days; a same-day rental is 1.
 */
final readonly class CalendarInclusive implements DayCounter
{
    public function __construct(private string $timezone) {}

    public function count(CarbonImmutable $pickup, CarbonImmutable $return): int
    {
        // Compare the local dates as UTC midnights, so a DST change could never skew the count.
        $from = CarbonImmutable::parse($pickup->setTimezone($this->timezone)->toDateString(), 'UTC');
        $to = CarbonImmutable::parse($return->setTimezone($this->timezone)->toDateString(), 'UTC');

        return max(1, intdiv($to->getTimestamp() - $from->getTimestamp(), 86_400) + 1);
    }
}
