<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * The only way a booking changes status (D3). Each change is one `booking-status` activity
 * row (D36); ending a booking frees its hold and stamps `closed_at`, and Reserved makes it a
 * firm booking (M9.7).
 */
class BookingStatusService
{
    public const LOG_NAME = 'booking-status';

    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  User|'renter'|'system'  $actor  staff, the renter (by token) or a scheduled job
     */
    public function transition(Booking $booking, BookingStatus $to, User|string $actor, ?string $reason = null): Booking
    {
        $from = $booking->status;

        if (! $from->canTransitionTo($to)) {
            throw BookingException::invalidTransition($from, $to);
        }

        DB::transaction(function () use ($booking, $from, $to, $actor, $reason) {
            $booking->status = $to;
            // KYC retention counts from here (M11.11).
            $booking->closed_at = $to->isTerminal() ? now() : null;
            $booking->save();

            if (in_array($to, [BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
                $this->availability->release($booking);
            }

            if ($to === BookingStatus::Reserved) {
                $this->availability->confirm($booking);
            }

            activity(self::LOG_NAME)->performedOn($booking)
                ->causedBy($actor instanceof User ? $actor : null)
                ->withProperties([
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason' => $reason,
                    'actor' => $actor instanceof User ? 'staff' : $actor,
                ])
                ->event($to->value)
                ->log("{$from->label()} → {$to->label()}");

            DB::afterCommit(fn () => BookingStatusChanged::dispatch($booking, $from, $to));
        });

        return $booking;
    }

    /**
     * The booking's status changes, oldest first.
     *
     * @return list<array{from: string, to: string, reason: string|null, actor: string, causer: string|null, at: string}>
     */
    public function timeline(Booking $booking): array
    {
        return array_values(Activity::query()
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->getKey())
            ->where('log_name', self::LOG_NAME)
            ->with('causer')
            ->oldest('id')
            ->get()
            ->map(fn (Activity $row) => [
                'from' => (string) $row->getProperty('from'),
                'to' => (string) $row->getProperty('to'),
                'reason' => $row->getProperty('reason'),
                'actor' => (string) $row->getProperty('actor'),
                'causer' => $row->causer?->getAttribute('name'),
                'at' => $row->created_at?->toIso8601String() ?? '',
            ])
            ->all());
    }
}
