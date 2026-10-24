<?php

namespace App\Exceptions\Domain;

use App\Enums\BookingStatus;

/**
 * A booking rule was broken. `field` names the form input to show the message on.
 */
class BookingException extends DomainException
{
    private function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function invalidTransition(BookingStatus $from, BookingStatus $to): self
    {
        return new self("A booking cannot move from {$from->label()} to {$to->label()}.");
    }

    public static function notEditable(): self
    {
        return new self('This booking has been submitted and can no longer be changed.');
    }

    public static function carNotBookable(): self
    {
        return new self('This car is not taking bookings at the moment.', 'pickup_at');
    }

    public static function carUnavailable(): self
    {
        return new self('This car is already booked for some of these dates. Please choose other dates.', 'pickup_at');
    }

    public static function pickupPassed(): self
    {
        return new self('The pickup time has passed. Please choose new dates.', 'pickup_at');
    }

    public static function stepsIncomplete(): self
    {
        return new self('Please complete the driver details before submitting.');
    }

    public static function noTermsBundle(): self
    {
        return new self('Our rental terms are being updated. Please try again shortly.');
    }

    public static function requestsClosed(): self
    {
        return new self('This booking has ended, so it can no longer be changed. Please contact us instead.', 'message');
    }

    public static function deliveryFeeNeeded(): self
    {
        return new self('Set the delivery fee before handing the car over.', 'balance_received');
    }

    public static function amountDiffersNeedsNote(): self
    {
        return new self('The amount differs from what is due. Add a note saying why.', 'notes');
    }

    public static function odometerBelowHandover(int $km): self
    {
        return new self("The reading cannot be lower than at handover ({$km} km).", 'odometer_km');
    }

    public static function refundAboveReservation(): self
    {
        return new self('The refund cannot be more than the reservation paid.', 'refund');
    }

    public static function refundOverrideNeedsReason(): self
    {
        return new self('Say why the refund differs from the suggested amount.', 'refund_override_reason');
    }

    public static function noRefundDue(): self
    {
        return new self('There is no refund to record for this booking.', 'reference');
    }

    public static function noDelivery(): self
    {
        return new self('This booking has no delivery or collection.');
    }

    public static function deliveryLocked(): self
    {
        return new self('The car has already been handed over, so the delivery fee can no longer change.');
    }

    public static function zoneFeeNeeded(): self
    {
        return new self('This zone is quoted by staff. Enter the fee.', 'fee');
    }

    protected function errorField(): ?string
    {
        return $this->field;
    }
}
