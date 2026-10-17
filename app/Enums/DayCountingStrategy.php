<?php

namespace App\Enums;

/**
 * How billable rental days are counted (PRD Q3, TECHNICAL_PLAN §9).
 */
enum DayCountingStrategy: string
{
    // Calendar dates from pickup to return, both inclusive.
    case CalendarInclusive = 'calendar_inclusive';

    // Each started 24 hours counts as a day, after a grace period.
    case Rolling24h = 'rolling_24h';

    // Each daytime window (e.g. 07:00–19:00) touched by the rental counts as a day.
    case DaytimeBlocks = 'daytime_blocks';

    public function label(): string
    {
        return match ($this) {
            self::CalendarInclusive => 'Calendar days, inclusive',
            self::Rolling24h => 'Rolling 24-hour periods',
            self::DaytimeBlocks => 'Daytime blocks',
        };
    }
}
