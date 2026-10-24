<?php

namespace App\Services\Booking;

use App\Enums\BookingBucket;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The bookings desk (M11.1, M11.10): the list staff work from, what needs doing today, and
 * the small desk actions that need no form. Desk actions log to `bookings`.
 */
class BookingDeskService
{
    public const LOG_NAME = 'bookings';

    public function __construct(private readonly BookingTokenService $tokens) {}

    /**
     * Upcoming and unpaid soonest pickup first; closed and all newest first. Dates are local
     * (business time zone) pickup dates.
     *
     * @param  array{bucket?: string|null, search?: string|null, from?: string|null, to?: string|null, car?: int|string|null, method?: string|null}  $filters
     * @return LengthAwarePaginator<int, Booking>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $bucket = BookingBucket::tryFrom((string) ($filters['bucket'] ?? '')) ?? BookingBucket::Upcoming;
        $zone = (string) config('baines.timezone');

        return Booking::query()
            ->with(['car:id,name', 'driver:id,booking_id,first_name,surname,phone', 'latestPayment.method'])
            ->whereIn('status', $bucket->statuses())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('driver', fn (Builder $query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")),
            ))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query
                ->where('pickup_at', '>=', CarbonImmutable::parse($from, $zone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query
                ->where('pickup_at', '<', CarbonImmutable::parse($to, $zone)->startOfDay()->addDay()->utc()))
            ->when($filters['car'] ?? null, fn (Builder $query, int|string $car) => $query->where('car_id', (int) $car))
            ->when($filters['method'] ?? null, fn (Builder $query, string $method) => $query
                ->whereHas('latestPayment', fn (Builder $query) => $query->where('payment_method_code', $method)))
            ->when(
                in_array($bucket, [BookingBucket::Unpaid, BookingBucket::Upcoming, BookingBucket::OnHire], true),
                fn (Builder $query) => $query->orderBy($bucket === BookingBucket::OnHire ? 'return_at' : 'pickup_at'),
                fn (Builder $query) => $query->orderByDesc('pickup_at'),
            )
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int> bucket => number of bookings
     */
    public function counts(): array
    {
        $byStatus = Booking::query()
            ->selectRaw('status, count(*) as total')
            ->where('status', '!=', BookingStatus::Draft->value)
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (BookingBucket::cases() as $bucket) {
            $counts[$bucket->value] = (int) collect($bucket->statuses())->sum(fn (BookingStatus $status) => $byStatus[$status->value] ?? 0);
        }

        return $counts;
    }

    /**
     * The dashboard's day (M11.10): pickups and returns due today, pickups that have passed
     * without a handover, and cars that should be back.
     *
     * @return array{pickups: Collection<int, Booking>, returns: Collection<int, Booking>, late_pickups: Collection<int, Booking>, overdue_returns: Collection<int, Booking>}
     */
    public function today(): array
    {
        $zone = (string) config('baines.timezone');
        $start = CarbonImmutable::now($zone)->startOfDay()->utc();
        $end = $start->addDay();
        $now = CarbonImmutable::now();

        $query = fn () => Booking::query()->with(['car:id,name', 'driver:id,booking_id,first_name,surname,phone'])->limit(20);

        return [
            'pickups' => $query()->where('status', BookingStatus::Reserved)->where('pickup_at', '>=', max($start, $now))->where('pickup_at', '<', $end)->orderBy('pickup_at')->get(),
            'returns' => $query()->where('status', BookingStatus::Active)->where('return_at', '>=', max($start, $now))->where('return_at', '<', $end)->orderBy('return_at')->get(),
            'late_pickups' => $query()->where('status', BookingStatus::Reserved)->where('pickup_at', '<', $now)->orderBy('pickup_at')->get(),
            'overdue_returns' => $query()->where('status', BookingStatus::Active)->where('return_at', '<', $now)->orderBy('return_at')->get(),
        ];
    }

    /**
     * A new renter link (M11.5). The old one stops working; staff send the new one themselves.
     */
    public function rotateLink(Booking $booking, User $actor): string
    {
        $token = $this->tokens->rotate($booking);

        activity(self::LOG_NAME)->performedOn($booking)->causedBy($actor)
            ->event('link-rotated')
            ->log('renter link replaced');

        return $token;
    }

    public function setLegalHold(Booking $booking, bool $hold, ?string $reason, User $actor): Booking
    {
        $booking->forceFill(['legal_hold' => $hold])->save();

        activity(self::LOG_NAME)->performedOn($booking)->causedBy($actor)
            ->withProperties(['legal_hold' => $hold, 'reason' => $reason])
            ->event($hold ? 'legal-hold-set' : 'legal-hold-lifted')
            ->log($hold ? 'legal hold set' : 'legal hold lifted');

        return $booking;
    }
}
