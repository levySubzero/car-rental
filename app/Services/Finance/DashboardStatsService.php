<?php

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Car;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The dashboard's figures and charts (M11C.4). Each widget is aggregated in PHP over its
 * period's rows, so it reads the same on SQLite and MySQL, and cached for five minutes. The
 * figures do not depend on who is looking; the controller picks which widgets to send.
 * Queue counts (payments to check, receipts, requests, messages) are not here: they stay live.
 */
class DashboardStatsService
{
    public const TTL_SECONDS = 300;

    public const WINDOW_DAYS = 30;

    public function __construct(private readonly FinanceService $finance) {}

    /**
     * Net revenue this month so far, and last month to the same day.
     *
     * @return array{this_month_minor: int, last_month_minor: int}
     */
    public function revenue(): array
    {
        return $this->remember('revenue', function () {
            [$current, $previous] = $this->monthToDate();

            return [
                'this_month_minor' => $this->finance->summary($current)['net_minor'],
                'last_month_minor' => $this->finance->summary($previous)['net_minor'],
            ];
        });
    }

    /**
     * Bookings submitted this month so far, and last month to the same day. Holds that
     * expired unpaid are left out.
     *
     * @return array{this_month: int, last_month: int}
     */
    public function bookings(): array
    {
        return $this->remember('bookings', function () {
            [$current, $previous] = $this->monthToDate();

            return [
                'this_month' => $this->submitted($current)->count(),
                'last_month' => $this->submitted($previous)->count(),
            ];
        });
    }

    /**
     * Share of the fleet's days booked over the last 30 days: booked days of firm bookings,
     * clipped to the window, over published cars × 30. Blackouts are not subtracted.
     *
     * @return array{percent: int, booked_days: float, car_days: int}
     */
    public function utilisation(): array
    {
        return $this->remember('utilisation', function () {
            $period = FinancePeriod::lastDays(self::WINDOW_DAYS);
            $carDays = Car::query()->published()->count() * $period->days();
            $booked = round($this->bookedDays($period)->sum(), 1);

            return [
                'percent' => $carDays > 0 ? (int) round(min(1, $booked / $carDays) * 100) : 0,
                'booked_days' => $booked,
                'car_days' => $carDays,
            ];
        });
    }

    /**
     * Money collected per day over the last 30 days.
     *
     * @return list<array{date: string, value: int}>
     */
    public function collectedSeries(): array
    {
        return $this->remember('collected-series', fn () => array_map(
            fn (array $day) => ['date' => $day['date'], 'value' => $day['collected_minor']],
            $this->finance->daily(FinancePeriod::lastDays(self::WINDOW_DAYS)),
        ));
    }

    /**
     * Bookings submitted per day over the last 30 days.
     *
     * @return list<array{date: string, value: int}>
     */
    public function bookingsSeries(): array
    {
        return $this->remember('bookings-series', function () {
            $period = FinancePeriod::lastDays(self::WINDOW_DAYS);
            $perDay = $this->submitted($period)
                ->get(['id', 'submitted_at'])
                ->countBy(fn (Booking $booking) => FinancePeriod::localDate($booking->submitted_at));

            return array_map(
                fn (string $date) => ['date' => $date, 'value' => (int) $perDay->get($date, 0)],
                $period->dates(),
            );
        });
    }

    /**
     * The most-booked cars over the last 30 days, by booked days.
     *
     * @return list<array{car_id: int, car: string, days: float}>
     */
    public function topCars(int $limit = 5): array
    {
        return $this->remember('top-cars', function () use ($limit) {
            $days = $this->bookedDays(FinancePeriod::lastDays(self::WINDOW_DAYS))->sortDesc()->take($limit);
            $names = Car::query()->whereKey($days->keys())->pluck('name', 'id');

            return array_values($days
                ->map(fn (float $booked, int $carId) => ['car_id' => $carId, 'car' => (string) $names->get($carId, 'Removed car'), 'days' => round($booked, 1)])
                ->all());
        });
    }

    /**
     * Booked days per car in the period: the part of each firm booking inside it.
     *
     * @return Collection<int, float>
     */
    private function bookedDays(FinancePeriod $period): Collection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::Reserved, BookingStatus::Active, BookingStatus::Completed])
            ->where('pickup_at', '<', $period->end)
            ->where('return_at', '>', $period->start)
            ->get(['id', 'car_id', 'pickup_at', 'return_at'])
            ->groupBy('car_id')
            ->map(fn (Collection $bookings) => (float) $bookings->sum(function (Booking $booking) use ($period) {
                $start = $booking->pickup_at->max($period->start);
                $end = $booking->return_at->min($period->end);

                return max(0, $start->diffInSeconds($end)) / 86_400;
            }));
    }

    /**
     * @return Builder<Booking>
     */
    private function submitted(FinancePeriod $period): Builder
    {
        return Booking::query()
            ->whereNotNull('submitted_at')
            ->where('status', '!=', BookingStatus::Expired)
            ->where('submitted_at', '>=', $period->start)
            ->where('submitted_at', '<', $period->end);
    }

    /**
     * This month from the 1st to today, and last month over the same days (cut short when
     * last month has fewer).
     *
     * @return array{0: FinancePeriod, 1: FinancePeriod}
     */
    private function monthToDate(): array
    {
        $current = FinancePeriod::thisMonth();
        $lastStart = $current->from->subMonthNoOverflow()->startOfMonth();
        $lastEnd = $lastStart->addDays($current->days() - 1)->min($lastStart->endOfMonth()->startOfDay());

        return [$current, FinancePeriod::between($lastStart, $lastEnd)];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(string $widget, Closure $callback): mixed
    {
        $today = CarbonImmutable::now(FinancePeriod::zone())->toDateString();

        return Cache::remember("dashboard:{$widget}:{$today}", self::TTL_SECONDS, $callback);
    }
}
