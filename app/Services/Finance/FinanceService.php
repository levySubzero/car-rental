<?php

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Enums\FinanceChannel;
use App\Enums\HandoverType;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\HandoverRecord;
use App\Models\PaymentSubmission;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Revenue worked out from the rows the desk already keeps (M11C, D43), with no ledger table.
 * Collected: verified payments (by `verified_at`) and what staff took at the handover and
 * the return (by `recorded_at`). Refunds: refunds marked paid (by `refunded_at`). Deposits
 * are held for the renter and are never revenue. KES minor units, Nairobi days.
 */
class FinanceService
{
    /**
     * Every movement in the period, oldest first.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function entries(FinancePeriod $period): Collection
    {
        $payments = PaymentSubmission::query()
            ->with(['booking:id,reference,car_id', 'booking.car:id,name', 'gatewayTransaction:id,gateway'])
            ->where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $period->start)
            ->where('verified_at', '<', $period->end)
            ->get()
            ->filter(fn (PaymentSubmission $payment) => (int) $payment->amount_minor > 0)
            ->map(fn (PaymentSubmission $payment) => new LedgerEntry(
                at: $payment->verified_at,
                kind: LedgerEntry::KIND_PAYMENT,
                amountMinor: (int) $payment->amount_minor,
                channel: FinanceChannel::forSubmission($payment, $payment->gatewayTransaction?->gateway),
                bookingId: $payment->booking_id,
                bookingReference: $payment->booking->reference,
                carId: $payment->booking->car_id,
                carName: $payment->booking->car->name,
                reference: $payment->reference,
            ));

        $handovers = HandoverRecord::query()
            ->with(['booking:id,reference,car_id', 'booking.car:id,name'])
            ->where('recorded_at', '>=', $period->start)
            ->where('recorded_at', '<', $period->end)
            ->get()
            ->map(fn (HandoverRecord $record) => new LedgerEntry(
                at: $record->recorded_at,
                kind: $record->type === HandoverType::Handover ? LedgerEntry::KIND_HANDOVER : LedgerEntry::KIND_RETURN,
                amountMinor: (int) $record->balance_received_minor + (int) $record->extra_charges_minor,
                channel: FinanceChannel::Handover,
                bookingId: $record->booking_id,
                bookingReference: $record->booking->reference,
                carId: $record->booking->car_id,
                carName: $record->booking->car->name,
                reference: null,
            ))
            ->filter(fn (LedgerEntry $entry) => $entry->amountMinor > 0);

        $refunds = Booking::query()
            ->with('car:id,name')
            ->where('refund_minor', '>', 0)
            ->where('refunded_at', '>=', $period->start)
            ->where('refunded_at', '<', $period->end)
            ->get(['id', 'reference', 'car_id', 'refund_minor', 'refund_reference', 'refunded_at'])
            ->map(fn (Booking $booking) => new LedgerEntry(
                at: $booking->refunded_at,
                kind: LedgerEntry::KIND_REFUND,
                amountMinor: -(int) $booking->refund_minor,
                channel: null,
                bookingId: $booking->id,
                bookingReference: $booking->reference,
                carId: $booking->car_id,
                carName: $booking->car->name,
                reference: $booking->refund_reference,
            ));

        return $payments->toBase()
            ->concat($handovers)
            ->concat($refunds)
            ->sortBy(fn (LedgerEntry $entry) => [$entry->at->getTimestamp(), $entry->bookingId])
            ->values();
    }

    /**
     * @return array{collected_minor: int, refunds_minor: int, net_minor: int, deposits_held_minor: int, outstanding_minor: int, outstanding_count: int}
     */
    public function summary(FinancePeriod $period): array
    {
        return [
            ...$this->totals($this->entries($period)),
            'deposits_held_minor' => $this->depositsHeld(),
            ...$this->outstanding(),
        ];
    }

    /**
     * Collected and refunded per local day, every day of the period present.
     *
     * @return list<array{date: string, collected_minor: int, refunds_minor: int}>
     */
    public function daily(FinancePeriod $period): array
    {
        return $this->dailyFrom($period, $this->entries($period));
    }

    /**
     * Everything the Revenue page shows, from one read of the period's rows.
     *
     * @return array{summary: array<string, int>, daily: list<array<string, mixed>>, channels: list<array<string, mixed>>, cars: list<array<string, mixed>>, entries: Collection<int, LedgerEntry>}
     */
    public function report(FinancePeriod $period, int $carLimit = 10): array
    {
        $entries = $this->entries($period);

        return [
            'summary' => [
                ...$this->totals($entries),
                'deposits_held_minor' => $this->depositsHeld(),
                ...$this->outstanding(),
            ],
            'daily' => $this->dailyFrom($period, $entries),
            'channels' => $this->channelsFrom($entries),
            'cars' => $this->carsFrom($entries, $carLimit),
            'entries' => $entries,
        ];
    }

    /**
     * The ledger newest first, one page of it. Volumes are a car-hire desk's, so the period's
     * entries are merged in memory rather than with a database union.
     *
     * @param  Collection<int, LedgerEntry>|null  $entries
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateLedger(FinancePeriod $period, int $perPage = 25, ?Collection $entries = null): LengthAwarePaginator
    {
        $entries = ($entries ?? $this->entries($period))->reverse()->values();
        $page = Paginator::resolveCurrentPage();

        return (new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->map(fn (LedgerEntry $entry) => $entry->toArray())->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        ))->withQueryString();
    }

    /**
     * Deposits taken at a handover whose car has not come back yet. Once the return is
     * recorded the deposit is refunded or settled against charges, so it is no longer held.
     */
    public function depositsHeld(): int
    {
        return (int) HandoverRecord::query()
            ->where('type', HandoverType::Handover)
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->from('handover_records as returns')
                ->whereColumn('returns.booking_id', 'handover_records.booking_id')
                ->where('returns.type', HandoverType::Return->value))
            ->sum('deposit_received_minor');
    }

    /**
     * Balances still to collect: firm bookings whose car has not been handed over yet.
     *
     * @return array{outstanding_minor: int, outstanding_count: int}
     */
    public function outstanding(): array
    {
        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::Reserved, BookingStatus::Active])
            ->whereDoesntHave('handover')
            ->where('balance_minor', '>', 0);

        return [
            'outstanding_minor' => (int) (clone $bookings)->sum('balance_minor'),
            'outstanding_count' => $bookings->count(),
        ];
    }

    /**
     * @param  Collection<int, LedgerEntry>  $entries
     * @return array{collected_minor: int, refunds_minor: int, net_minor: int}
     */
    private function totals(Collection $entries): array
    {
        $collected = (int) $entries->reject->isRefund()->sum('amountMinor');
        $refunds = (int) -$entries->filter->isRefund()->sum('amountMinor');

        return [
            'collected_minor' => $collected,
            'refunds_minor' => $refunds,
            'net_minor' => $collected - $refunds,
        ];
    }

    /**
     * @param  Collection<int, LedgerEntry>  $entries
     * @return list<array{date: string, collected_minor: int, refunds_minor: int}>
     */
    private function dailyFrom(FinancePeriod $period, Collection $entries): array
    {
        $byDate = $entries->groupBy(fn (LedgerEntry $entry) => $entry->date());

        return array_map(function (string $date) use ($byDate) {
            /** @var Collection<int, LedgerEntry> $day */
            $day = $byDate->get($date, collect());
            $totals = $this->totals($day);

            return ['date' => $date, 'collected_minor' => $totals['collected_minor'], 'refunds_minor' => $totals['refunds_minor']];
        }, $period->dates());
    }

    /**
     * @param  Collection<int, LedgerEntry>  $entries
     * @return list<array{channel: string, label: string, amount_minor: int, count: int}>
     */
    private function channelsFrom(Collection $entries): array
    {
        $channels = [];

        foreach ($entries as $entry) {
            if ($entry->channel === null) {
                continue;
            }

            $row = $channels[$entry->channel->value] ?? ['channel' => $entry->channel->value, 'label' => $entry->channel->label(), 'amount_minor' => 0, 'count' => 0];
            $row['amount_minor'] += $entry->amountMinor;
            $row['count']++;
            $channels[$entry->channel->value] = $row;
        }

        usort($channels, fn (array $a, array $b) => $b['amount_minor'] <=> $a['amount_minor']);

        return $channels;
    }

    /**
     * @param  Collection<int, LedgerEntry>  $entries
     * @return list<array{car_id: int|null, car: string, collected_minor: int, refunds_minor: int, net_minor: int}>
     */
    private function carsFrom(Collection $entries, int $limit): array
    {
        return array_values($entries
            ->groupBy(fn (LedgerEntry $entry) => (string) $entry->carId)
            ->map(fn (Collection $group) => [
                'car_id' => $group->first()->carId,
                'car' => $group->first()->carName ?? 'Removed car',
                ...$this->totals($group),
            ])
            ->sortByDesc('net_minor')
            ->take($limit)
            ->values()
            ->all());
    }
}
