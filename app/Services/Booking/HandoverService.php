<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Enums\HandoverType;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\HandoverRecord;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The car going out and coming back (M11.6, M11.7). Handover takes the balance and the
 * security deposit and puts the booking on hire; return reads the odometer against the
 * mileage allowance, settles the deposit and completes it. Amounts are KES.
 */
class HandoverService
{
    public function __construct(private readonly BookingStatusService $statuses) {}

    /**
     * A note is needed when the balance taken differs from the balance due.
     */
    public function recordHandover(Booking $booking, int $odometerKm, FuelLevel $fuel, Money $balanceReceived, Money $depositReceived, ?string $notes, User $actor): HandoverRecord
    {
        $notes = $this->clean($notes);

        return DB::transaction(function () use ($booking, $odometerKm, $fuel, $balanceReceived, $depositReceived, $notes, $actor) {
            $booking = $this->lock($booking, BookingStatus::Active);

            if ($booking->deliveryPending()) {
                throw BookingException::deliveryFeeNeeded();
            }

            if ($balanceReceived->minor !== (int) $booking->balance_minor && $notes === null) {
                throw BookingException::amountDiffersNeedsNote();
            }

            $record = $booking->handoverRecords()->create([
                'type' => HandoverType::Handover,
                'odometer_km' => $odometerKm,
                'fuel_level' => $fuel,
                'balance_received_minor' => $balanceReceived->minor,
                'deposit_received_minor' => $depositReceived->minor,
                'notes' => $notes,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);

            $this->statuses->transition($booking, BookingStatus::Active, $actor, $notes);

            return $record;
        });
    }

    /**
     * A note is needed when part of the deposit is kept.
     */
    public function recordReturn(Booking $booking, int $odometerKm, FuelLevel $fuel, Money $depositRefunded, Money $extraCharges, ?string $notes, User $actor): HandoverRecord
    {
        $notes = $this->clean($notes);

        return DB::transaction(function () use ($booking, $odometerKm, $fuel, $depositRefunded, $extraCharges, $notes, $actor) {
            $booking = $this->lock($booking, BookingStatus::Completed);
            $handover = $booking->handover;

            if ($handover !== null && $odometerKm < $handover->odometer_km) {
                throw BookingException::odometerBelowHandover($handover->odometer_km);
            }

            if ($depositRefunded->minor < (int) $handover?->deposit_received_minor && $notes === null) {
                throw BookingException::amountDiffersNeedsNote();
            }

            $record = $booking->handoverRecords()->create([
                'type' => HandoverType::Return,
                'odometer_km' => $odometerKm,
                'fuel_level' => $fuel,
                'deposit_refunded_minor' => $depositRefunded->minor,
                'excess_km' => $handover === null ? null : $this->excessKm($booking, $odometerKm - $handover->odometer_km),
                'extra_charges_minor' => $extraCharges->minor,
                'notes' => $notes,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);

            $this->statuses->transition($booking, BookingStatus::Completed, $actor, $notes);

            return $record;
        });
    }

    /**
     * The kilometres over the allowance frozen in the quote: per day × billable days, or
     * none when the hire had unlimited mileage.
     */
    public function excessKm(Booking $booking, int $drivenKm): int
    {
        $perDay = $booking->quote['mileage_km_per_day'] ?? null;

        if ($perDay === null) {
            return 0;
        }

        return max(0, $drivenKm - (int) $perDay * (int) ($booking->quote['days'] ?? 0));
    }

    /**
     * Lock the booking and check it can move to $to, so a double click records once.
     */
    private function lock(Booking $booking, BookingStatus $to): Booking
    {
        $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

        if (! $locked->status->canTransitionTo($to)) {
            throw BookingException::invalidTransition($locked->status, $to);
        }

        return $locked;
    }

    private function clean(?string $text): ?string
    {
        return $text !== null && trim($text) !== '' ? trim($text) : null;
    }
}
