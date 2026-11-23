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

    // A booking that ends here never went on hire.
    private const ENDS_UNHIRED = [BookingStatus::Cancelled, BookingStatus::NoShow, BookingStatus::Expired];

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

        // A hire cannot end unhired while extensions of it are paid for: those are refunded first.
        if (! $booking->isExtension() && in_array($to, self::ENDS_UNHIRED, true)
            && $booking->extensions()->whereIn('status', [BookingStatus::Reserved, BookingStatus::Active])->exists()) {
            throw BookingException::extensionsStillLive();
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

            $booking->isExtension() ? $this->followRoot($booking, $to, $actor) : $this->carryExtensions($booking, $to, $actor);
        });

        return $booking;
    }

    /**
     * An extension moved (D50): the root's end follows its paid extensions, and one paid for
     * while the car is already out goes straight on hire.
     *
     * @param  User|'renter'|'system'  $actor
     */
    private function followRoot(Booking $extension, BookingStatus $to, User|string $actor): void
    {
        $root = Booking::query()->find($extension->root_booking_id);

        if ($root === null) {
            return;
        }

        if (in_array($to, [BookingStatus::Reserved, ...self::ENDS_UNHIRED], true)) {
            $this->syncChainEnd($root);
        }

        if ($to === BookingStatus::Reserved && $root->status === BookingStatus::Active) {
            $this->transition($extension, BookingStatus::Active, $actor, 'The hire it extends is already on the road.');
        }
    }

    /**
     * The root moved: its extensions go on hire and come back with it, and one still being
     * asked for or paid lapses when the hire ends without happening.
     *
     * @param  User|'renter'|'system'  $actor
     */
    private function carryExtensions(Booking $root, BookingStatus $to, User|string $actor): void
    {
        $follow = match (true) {
            $to === BookingStatus::Active => [BookingStatus::Reserved, BookingStatus::Active],
            $to === BookingStatus::Completed => [BookingStatus::Active, BookingStatus::Completed],
            // Nothing paid is left by then (checked above); unpaid ones are cancelled.
            in_array($to, self::ENDS_UNHIRED, true) => null,
            default => false,
        };

        if ($follow === false) {
            return;
        }

        foreach ($root->extensions()->get() as $extension) {
            if ($follow !== null && $extension->status === $follow[0]) {
                $this->transition($extension, $follow[1], $actor, $follow[1] === BookingStatus::Active ? 'Handed over with the hire it extends.' : 'Returned with the hire it extends.');
            } elseif ($to !== BookingStatus::Active && in_array($extension->status, Booking::PENDING_EXTENSION, true)) {
                // The car is back or never left: an extension still unpaid has nothing to extend.
                $extension->forceFill(['cancellation_reason' => "The booking it extended ({$root->reference}) has ended."])->save();
                $this->transition($extension, BookingStatus::Cancelled, $actor, 'The booking it extended has ended.');
            }
        }
    }

    /**
     * Where the root's chain now ends: its last paid extension's return, or nowhere.
     */
    public function syncChainEnd(Booking $root): void
    {
        $end = $root->extensions()->whereIn('status', Booking::LIVE_EXTENSION)->max('return_at');

        $root->forceFill(['extended_until' => $end])->save();
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
