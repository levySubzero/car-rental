<?php

use App\Support\DateRange;
use Carbon\CarbonImmutable;

function range_of(string $start, string $end): DateRange
{
    return new DateRange(CarbonImmutable::parse($start), CarbonImmutable::parse($end));
}

it('detects overlapping ranges', function () {
    expect(range_of('2026-11-01 10:00', '2026-11-05 10:00')->overlaps(range_of('2026-11-04 09:00', '2026-11-06 10:00')))->toBeTrue()
        ->and(range_of('2026-11-01 10:00', '2026-11-05 10:00')->overlaps(range_of('2026-11-02 00:00', '2026-11-03 00:00')))->toBeTrue();
});

it('treats back-to-back ranges as free', function () {
    expect(range_of('2026-11-01 10:00', '2026-11-05 10:00')->overlaps(range_of('2026-11-05 10:00', '2026-11-07 10:00')))->toBeFalse();
});

it('pads by a turnaround buffer', function () {
    $padded = range_of('2026-11-01 10:00', '2026-11-05 10:00')->pad(0, 60);

    expect($padded->overlaps(range_of('2026-11-05 10:30', '2026-11-07 10:00')))->toBeTrue()
        ->and($padded->end->format('H:i'))->toBe('11:00');
});

it('contains its start but not its end', function () {
    $range = range_of('2026-11-01 10:00', '2026-11-05 10:00');

    expect($range->contains(CarbonImmutable::parse('2026-11-01 10:00')))->toBeTrue()
        ->and($range->contains(CarbonImmutable::parse('2026-11-05 10:00')))->toBeFalse()
        ->and($range->minutes())->toBe(4 * 24 * 60);
});

it('rejects an end before the start', function () {
    range_of('2026-11-05', '2026-11-01');
})->throws(InvalidArgumentException::class);
