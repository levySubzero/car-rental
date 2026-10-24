<?php

namespace App\Enums;

/**
 * Where an online payment stands (M11B, D42). Initiated waits for the gateway; paid money
 * either confirms its booking (matched) or waits for staff: needs review when it could not be
 * applied safely, unmatched when a paybill reference names no booking. Staff close those as
 * matched or refunded.
 */
enum GatewayTransactionStatus: string
{
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';
    case Unmatched = 'unmatched';
    case NeedsReview = 'needs_review';
    case Matched = 'matched';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Waiting for the renter',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Unmatched => 'No matching booking',
            self::NeedsReview => 'Needs review',
            self::Matched => 'Matched',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Money received that staff must still deal with.
     */
    public function awaitsStaff(): bool
    {
        return $this === self::Unmatched || $this === self::NeedsReview;
    }
}
