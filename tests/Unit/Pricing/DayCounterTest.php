<?php

use App\Services\Pricing\DayCounter\CalendarInclusive;
use App\Services\Pricing\DayCounter\DaytimeBlocks;
use App\Services\Pricing\DayCounter\Rolling24h;
use Carbon\CarbonImmutable;

function nbo(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'Africa/Nairobi');
}

it('counts calendar dates inclusively in Nairobi time', function (string $from, string $to, int $days) {
    expect((new CalendarInclusive('Africa/Nairobi'))->count(nbo($from), nbo($to)))->toBe($days);
})->with([
    'same day' => ['2026-11-02 10:00', '2026-11-02 18:00', 1],
    'one minute past midnight' => ['2026-11-02 23:59', '2026-11-03 00:00', 2],
    'three dates' => ['2026-11-02 10:00', '2026-11-04 09:00', 3],
    'across a month end' => ['2026-10-30 10:00', '2026-11-02 10:00', 4],
    'across a leap day' => ['2028-02-28 10:00', '2028-03-01 10:00', 3],
]);

it('uses the business time zone, not UTC, for calendar dates', function () {
    // 23:30–01:00 in Nairobi is two dates; in UTC (20:30–22:00) it would be one.
    $counter = new CalendarInclusive('Africa/Nairobi');

    expect($counter->count(CarbonImmutable::parse('2026-11-02 20:30', 'UTC'), CarbonImmutable::parse('2026-11-02 22:00', 'UTC')))->toBe(2);
});

it('counts rolling 24-hour periods with a grace', function (int $grace, string $to, int $days) {
    expect((new Rolling24h($grace))->count(nbo('2026-11-02 10:00'), nbo($to)))->toBe($days);
})->with([
    'under a day' => [60, '2026-11-02 11:00', 1],
    'exactly a day' => [60, '2026-11-03 10:00', 1],
    'inside the grace' => [60, '2026-11-03 11:00', 1],
    'one minute past the grace' => [60, '2026-11-03 11:01', 2],
    'no grace' => [0, '2026-11-03 10:01', 2],
    'a week' => [60, '2026-11-09 10:00', 7],
]);

it('counts each daytime window the rental overlaps', function (string $from, string $to, int $days) {
    expect((new DaytimeBlocks('Africa/Nairobi', '07:00', '19:00'))->count(nbo($from), nbo($to)))->toBe($days);
})->with([
    'one window' => ['2026-11-02 07:00', '2026-11-02 19:00', 1],
    'night only' => ['2026-11-02 19:00', '2026-11-03 07:00', 1],
    'evening to morning' => ['2026-11-02 18:00', '2026-11-03 08:00', 2],
    'edges do not count' => ['2026-11-02 19:00', '2026-11-04 07:00', 1],
    'a minute inside each edge' => ['2026-11-02 18:59', '2026-11-04 07:01', 3],
    'three days' => ['2026-11-02 10:00', '2026-11-04 10:00', 3],
]);
