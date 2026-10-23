<?php

use App\Services\Booking\BookingReferenceGenerator;
use Carbon\CarbonImmutable;

it('numbers references per year in the business time zone', function () {
    $generator = app(BookingReferenceGenerator::class);

    expect($generator->next(CarbonImmutable::parse('2026-06-01 10:00')))->toBe('BE-2026-000001')
        ->and($generator->next(CarbonImmutable::parse('2026-06-01 11:00')))->toBe('BE-2026-000002')
        // 22:00 UTC on 31 December is already New Year in Nairobi.
        ->and($generator->next(CarbonImmutable::parse('2026-12-31 22:00', 'UTC')))->toBe('BE-2027-000001')
        ->and($generator->next(CarbonImmutable::parse('2026-07-01 10:00')))->toBe('BE-2026-000003');
});
