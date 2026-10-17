<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A half-open period [start, end): a return at 10:00 and a pickup at 10:00
 * on the same car do not overlap.
 */
final readonly class DateRange
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(DateTimeInterface $start, DateTimeInterface $end)
    {
        $this->start = CarbonImmutable::instance($start);
        $this->end = CarbonImmutable::instance($end);

        if ($this->end->lessThanOrEqualTo($this->start)) {
            throw new InvalidArgumentException('A date range must end after it starts.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    public function contains(DateTimeInterface $moment): bool
    {
        return $this->start->lessThanOrEqualTo($moment) && $this->end->greaterThan($moment);
    }

    /**
     * Widen the range on both sides, e.g. by the car turnaround buffer.
     */
    public function pad(int $minutesBefore, int $minutesAfter): self
    {
        return new self($this->start->subMinutes($minutesBefore), $this->end->addMinutes($minutesAfter));
    }

    public function minutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }
}
