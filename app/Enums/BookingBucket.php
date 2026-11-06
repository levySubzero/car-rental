<?php

namespace App\Enums;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

/**
 * The tabs of the desk's two screens (M11.1): Requests holds what is not yet paid for,
 * Bookings what is. A cancellation lands on the screen it came from, so paid_at decides it.
 * Drafts are the renter's own and never appear.
 */
enum BookingBucket: string
{
    case New = 'new';
    case Unpaid = 'unpaid';
    case Declined = 'declined';
    case Upcoming = 'upcoming';
    case OnHire = 'on_hire';
    case Closed = 'closed';
    case All = 'all';

    /**
     * @return list<self>
     */
    public static function requests(): array
    {
        return [self::New, self::Unpaid, self::Declined];
    }

    /**
     * @return list<self>
     */
    public static function bookings(): array
    {
        return [self::Upcoming, self::OnHire, self::Closed, self::All];
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::New => $query->where('status', BookingStatus::Requested),
            self::Unpaid => $query->whereIn('status', [BookingStatus::AwaitingPayment, BookingStatus::PaymentSubmitted, BookingStatus::AwaitingCash, BookingStatus::PaymentRejected]),
            self::Declined => $query->whereIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])->whereNull('paid_at'),
            self::Upcoming => $query->where('status', BookingStatus::Reserved),
            self::OnHire => $query->where('status', BookingStatus::Active),
            self::Closed => $this->paidOr($query, [BookingStatus::Completed, BookingStatus::NoShow]),
            self::All => $this->paidOr($query, [BookingStatus::Reserved, BookingStatus::Active, BookingStatus::Completed, BookingStatus::NoShow]),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Unpaid => 'Awaiting payment',
            self::Declined, self::Closed => 'Closed',
            self::Upcoming => 'Upcoming',
            self::OnHire => 'On hire',
            self::All => 'All',
        };
    }

    /**
     * @param  Builder<Booking>  $query
     * @param  list<BookingStatus>  $statuses
     * @return Builder<Booking>
     */
    private function paidOr(Builder $query, array $statuses): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereIn('status', $statuses)
            ->orWhere(fn (Builder $query) => $query->where('status', BookingStatus::Cancelled)->whereNotNull('paid_at')));
    }
}
