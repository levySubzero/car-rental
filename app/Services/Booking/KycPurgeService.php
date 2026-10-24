<?php

namespace App\Services\Booking;

use App\Enums\BookingDocument;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Media\MediaService;
use App\Settings\RetentionSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Retention (M11.11, Data Protection Act 2019). KYC is kept for `kyc_retention_months` after
 * a booking ends, then its documents are deleted and the driver's identity numbers wiped.
 * The name and contacts stay with the booking record; the voucher and payment proofs are
 * financial records and stay too. A legal hold stops the purge. Drafts that were never
 * submitted are deleted outright after `draft_retention_days`.
 */
class KycPurgeService
{
    public const LOG_NAME = 'retention';

    public function __construct(
        private readonly RetentionSettings $settings,
        private readonly MediaService $media,
    ) {}

    /**
     * @return array{bookings: int, drafts: int}
     */
    public function purge(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $counts = ['bookings' => 0, 'drafts' => 0];

        Booking::query()
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $now->subMonths($this->settings->kyc_retention_months))
            ->where('legal_hold', false)
            ->whereNull('kyc_purged_at')
            ->whereNotNull('reference')
            ->with('driver')
            ->chunkById(100, function ($bookings) use (&$counts) {
                foreach ($bookings as $booking) {
                    $this->purgeBooking($booking);
                    $counts['bookings']++;
                }
            });

        Booking::query()
            ->where('status', BookingStatus::Expired)
            ->whereNull('reference')
            ->where('updated_at', '<', $now->subDays($this->settings->draft_retention_days))
            ->chunkById(100, function ($drafts) use (&$counts) {
                foreach ($drafts as $draft) {
                    // HasMedia removes its uploads with it; media:cleanup purges the files.
                    $draft->delete();
                    $counts['drafts']++;
                }
            });

        return $counts;
    }

    public function purgeBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $files = $booking->media()->whereIn('collection', self::collections())->get();

            foreach ($files as $file) {
                $this->media->delete($file);
            }

            // The saving hook re-hashes the blank id number, which identifies no one.
            $booking->driver?->forceFill([
                'id_number' => '',
                'kra_pin' => null,
                'licence_number' => '',
            ])->save();

            $booking->forceFill(['kyc_purged_at' => now()])->save();

            activity(self::LOG_NAME)->performedOn($booking)
                ->withProperties(['files' => $files->count()])
                ->event('kyc-purged')
                ->log('KYC purged');
        });
    }

    /**
     * @return list<string>
     */
    public static function collections(): array
    {
        return array_map(fn (BookingDocument $document) => $document->collection(), BookingDocument::cases());
    }
}
