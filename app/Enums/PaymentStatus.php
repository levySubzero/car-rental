<?php

namespace App\Enums;

/**
 * Where one payment submission stands (M9). A booking has at most one pending submission;
 * a rejected one stays on record next to the retry.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for checks',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }
}
