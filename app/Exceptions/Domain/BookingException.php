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

    public static function cannotChange(BookingStatus $status, string $action): self
    {
        return new self("A booking that is {$status->label()} cannot be {$action}.");
    }

    public static function carNotBookable(): self
    {
        return new self('This car is not taking bookings at the moment.', 'pickup_at');
    }

    public static function carUnavailable(): self
    {
        return new self('This car is already booked for some of these dates. Please choose other dates.', 'pickup_at');
    }

    public static function carTakenForRequest(): self
    {
        return new self('This car has been taken for some of these dates, so the request cannot be accepted. Decline it with a reason.');
    }

    public static function adjustedTotalTooLow(): self
    {
        return new self('The total cannot be less than the delivery fee already set on this booking.', 'total');
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

    public static function refundFeeAboveRefund(): self
    {
        return new self('The transaction cost cannot be more than the refund.', 'refund_fee');
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

    public static function notExtendable(BookingStatus $status): self
    {
        return new self("A booking that is {$status->label()} cannot be extended.", 'return_at');
    }

    public static function extensionOfExtension(): self
    {
        return new self('Extend the original booking, not one of its extensions.', 'return_at');
    }

    public static function extensionPending(string $reference): self
    {
        return new self("Extension {$reference} is still waiting to be accepted or paid. Finish or cancel it first.", 'return_at');
    }

    public static function extensionTooShort(): self
    {
        return new self('The new return must be after the current one.', 'return_at');
    }

    public static function extensionsStillLive(): self
    {
        return new self('This booking has paid extensions. Cancel them first.');
    }

    public static function followsRoot(string $reference): self
    {
        return new self("This is an extension: hand-over and return are recorded on {$reference}.");
    }

    public static function changeHasExtensions(): self
    {
        return new self('This booking has extensions, so its trip can no longer be edited. Extend it again, or cancel the extensions first.');
    }

    protected function errorField(): ?string
    {
        return $this->field;
    }
}
