<?php

namespace App\Enums;

/**
 * The tabs of the bookings desk (M11.1): each groups the statuses staff handle together.
 * Drafts are the renter's own and never appear.
 */
enum BookingBucket: string
{
    case Unpaid = 'unpaid';
    case Upcoming = 'upcoming';
    case OnHire = 'on_hire';
    case Closed = 'closed';
    case All = 'all';

    /**
     * @return list<BookingStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::Unpaid => [BookingStatus::AwaitingPayment, BookingStatus::PaymentSubmitted, BookingStatus::AwaitingCash, BookingStatus::PaymentRejected],
            self::Upcoming => [BookingStatus::Reserved],
            self::OnHire => [BookingStatus::Active],
            self::Closed => [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow, BookingStatus::Expired],
            self::All => array_values(array_filter(BookingStatus::cases(), fn (BookingStatus $status) => $status !== BookingStatus::Draft)),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Upcoming => 'Upcoming',
            self::OnHire => 'On hire',
            self::Closed => 'Closed',
            self::All => 'All',
        };
    }

    public static function for(BookingStatus $status): self
    {
        foreach ([self::Unpaid, self::Upcoming, self::OnHire, self::Closed] as $bucket) {
            if (in_array($status, $bucket->statuses(), true)) {
                return $bucket;
            }
        }

        return self::All;
    }
}
