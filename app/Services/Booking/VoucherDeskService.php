<?php

namespace App\Services\Booking;

use App\Enums\VoucherBucket;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Vouchers screen: every confirmed booking's voucher by where it stands, with the bookings
 * desk's search and filters.
 */
class VoucherDeskService
{
    public function __construct(private readonly BookingDeskService $desk) {}

    /**
     * Not sent and sent soonest pickup first; used, void and all newest first.
     *
     * @param  array{bucket?: string|null, search?: string|null, from?: string|null, to?: string|null, car?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Booking>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $bucket = VoucherBucket::tryFrom((string) ($filters['bucket'] ?? '')) ?? VoucherBucket::NotSent;

        return $this->desk->filter($bucket->apply(Booking::query()), $filters)
            ->with(['car:id,name', 'driver:id,booking_id,first_name,surname,phone,email'])
            ->when(
                $bucket->soonestFirst(),
                fn (Builder $query) => $query->orderBy('pickup_at'),
                fn (Builder $query) => $query->orderByDesc('pickup_at'),
            )
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int> bucket => number of vouchers
     */
    public function counts(): array
    {
        $counts = [];

        foreach (VoucherBucket::cases() as $bucket) {
            $counts[$bucket->value] = $bucket->apply(Booking::query())->count();
        }

        return $counts;
    }
}
