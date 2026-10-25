<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;

/**
 * A run of whole business-time-zone days (Africa/Nairobi), both ends included. `start` and
 * `end` are the UTC instants the rows are compared with; `end` is exclusive.
 */
final readonly class FinancePeriod
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    private function __construct(public CarbonImmutable $from, public CarbonImmutable $to)
    {
        $this->start = $from->startOfDay()->utc();
        $this->end = $to->startOfDay()->addDay()->utc();
    }

    /**
     * Local dates as `Y-m-d`; this month so far when either is missing.
     */
    public static function fromDates(?string $from, ?string $to): self
    {
        $zone = self::zone();

        if ($from === null || $to === null) {
            return self::thisMonth();
        }

        return new self(CarbonImmutable::parse($from, $zone), CarbonImmutable::parse($to, $zone));
    }

    public static function thisMonth(): self
    {
        $today = CarbonImmutable::now(self::zone());

        return new self($today->startOfMonth(), $today->startOfDay());
    }

    /**
     * The last `$days` days, today included.
     */
    public static function lastDays(int $days): self
    {
        $today = CarbonImmutable::now(self::zone())->startOfDay();

        return new self($today->subDays($days - 1), $today);
    }

    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->setTimezone(self::zone()), $to->setTimezone(self::zone()));
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Every local date in the period, as `Y-m-d`.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from->startOfDay(); $day->lte($this->to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public static function zone(): string
    {
        return (string) config('baines.timezone');
    }

    /**
     * The local date an instant falls on.
     */
    public static function localDate(CarbonImmutable $at): string
    {
        return $at->setTimezone(self::zone())->toDateString();
    }
}
