<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Booking references such as BE-2026-000123: a counter per year, in the business time zone.
 * Call inside the submit transaction: the year's row is locked until it commits, so two
 * submits never get the same number.
 */
class BookingReferenceGenerator
{
    public function next(?CarbonImmutable $at = null): string
    {
        $year = ($at ?? CarbonImmutable::now())->setTimezone((string) config('baines.timezone'))->year;

        $sequence = DB::transaction(function () use ($year) {
            DB::table('booking_reference_sequences')->insertOrIgnore(['year' => $year, 'last' => 0]);

            $last = (int) DB::table('booking_reference_sequences')->where('year', $year)->lockForUpdate()->value('last');
            DB::table('booking_reference_sequences')->where('year', $year)->update(['last' => $last + 1]);

            return $last + 1;
        });

        /** @var array{prefix: string, format: string, sequence_padding: int} $config */
        $config = config('baines.booking_reference');

        return strtr($config['format'], [
            '{prefix}' => $config['prefix'],
            '{year}' => (string) $year,
            '{seq}' => str_pad((string) $sequence, $config['sequence_padding'], '0', STR_PAD_LEFT),
        ]);
    }
}
