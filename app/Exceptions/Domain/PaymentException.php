<?php

namespace App\Exceptions\Domain;

use App\Models\Booking;

/**
 * A payment rule was broken (M9). `field` names the form input to show the message on.
 */
class PaymentException extends DomainException
{
    private function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function notAwaitingPayment(Booking $booking): self
    {
        return new self("This booking is {$booking->status->label()}, so it is not taking a payment.");
    }

    public static function alreadySubmitted(): self
    {
        return new self('A payment for this booking is already waiting to be checked.');
    }

    public static function methodUnavailable(): self
    {
        return new self('This payment method is not available at the moment. Please choose another.', 'method');
    }

    public static function alreadyHandled(): self
    {
        return new self('This payment has already been verified or rejected.');
    }

    public static function amountNotPositive(): self
    {
        return new self('Enter the amount received.', 'amount');
    }

    public static function partialNotAllowed(): self
    {
        return new self('The amount is less than the reservation due. Accepting a part payment needs the accept-partial permission.', 'amount');
    }

    public static function partialNeedsNote(): self
    {
        return new self('Add a note explaining why a part payment is accepted.', 'note');
    }

    public static function cashCannotBeRejected(): self
    {
        return new self('A cash booking has nothing to reject: verify it when the cash arrives, or let it lapse.');
    }

    public static function carNoLongerFree(): self
    {
        return new self('The hold on this car has lapsed and the dates have since been taken. The booking cannot be reserved.');
    }

    public static function gatewayUnavailable(string $method): self
    {
        return new self("{$method} could not take the payment just now. Please try again in a moment, or pay another way.");
    }

    public static function invalidMpesaPhone(): self
    {
        return new self('Enter a Kenyan mobile number registered for M-Pesa, such as 0712 345 678.', 'phone');
    }

    public static function stkJustSent(): self
    {
        return new self('A payment request was just sent to your phone. Check it, or wait a minute before asking again.', 'phone');
    }

    public static function noRenterEmail(): self
    {
        return new self('This booking has no email address to send the card receipt to. Please choose another method.', 'method');
    }

    public static function unknownOnlinePayment(): self
    {
        return new self('We could not find that card payment for this booking.');
    }

    public static function receiptAlreadyResolved(): self
    {
        return new self('This payment has already been dealt with.');
    }

    protected function errorField(): ?string
    {
        return $this->field;
    }
}
