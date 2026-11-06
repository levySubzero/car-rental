<?php

namespace App\Enums;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

/**
 * The tabs of the Vouchers screen. A voucher is the booking's, so its state comes from the
 * booking: live while reserved or on hire, used once the hire is over, void when a paid booking
 * is cancelled or a no-show. Not sent matches Booking::voucherUnsent(); a client staff entered
 * with no email gets the voucher by hand, so theirs counts as sent.
 */
enum VoucherBucket: string
{
    case NotSent = 'not_sent';
    case Sent = 'sent';
    case Used = 'used';
    case Void = 'void';
    case All = 'all';

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::NotSent => $this->live($query)->whereNull('voucher_sent_at')->where(fn (Builder $query) => $query
                ->where('source', '!=', BookingSource::Admin)
                ->orWhereHas('driver', fn (Builder $driver) => $driver->whereNotNull('email')->where('email', '!=', ''))),
            self::Sent => $this->live($query)->where(fn (Builder $query) => $query
                ->whereNotNull('voucher_sent_at')
                ->orWhere(fn (Builder $query) => $query
                    ->where('source', BookingSource::Admin)
                    ->whereDoesntHave('driver', fn (Builder $driver) => $driver->whereNotNull('email')->where('email', '!=', '')))),
            self::Used => $query->where('status', BookingStatus::Completed),
            self::Void => $this->void($query),
            self::All => $query->where(fn (Builder $query) => $query
                ->whereIn('status', [BookingStatus::Reserved, BookingStatus::Active, BookingStatus::Completed])
                ->orWhere(fn (Builder $query) => $this->void($query))),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotSent => 'Not sent',
            self::Sent => 'Sent',
            self::Used => 'Used',
            self::Void => 'Void',
            self::All => 'All',
        };
    }

    /**
     * Soonest pickup first while there is still something to hand over.
     */
    public function soonestFirst(): bool
    {
        return in_array($this, [self::NotSent, self::Sent], true);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function live(Builder $query): Builder
    {
        return $query->whereIn('status', [BookingStatus::Reserved, BookingStatus::Active]);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function void(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('status', BookingStatus::NoShow)
            ->orWhere(fn (Builder $query) => $query->where('status', BookingStatus::Cancelled)->whereNotNull('paid_at')));
    }
}
