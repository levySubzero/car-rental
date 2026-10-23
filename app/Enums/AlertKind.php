<?php

namespace App\Enums;

/**
 * What an in-app staff alert is about (M10B). Each kind names the permissions whose holders
 * receive it (any of them) and the icon the bell shows. A new alert is a case here plus one
 * AdminAlerts::send() call where it happens.
 */
enum AlertKind: string
{
    case NewBooking = 'new_booking';
    case PaymentSubmitted = 'payment_submitted';
    case ContactMessage = 'contact_message';
    case BookingRequest = 'booking_request';

    /**
     * @return list<Permission>
     */
    public function audience(): array
    {
        return match ($this) {
            self::NewBooking => [Permission::BookingsView, Permission::PaymentsView],
            self::PaymentSubmitted => [Permission::PaymentsView],
            self::ContactMessage => [Permission::ContactMessagesView],
            self::BookingRequest => [Permission::BookingRequestsView],
        };
    }

    /**
     * A lucide icon name, mapped to a component by the bell.
     */
    public function icon(): string
    {
        return match ($this) {
            self::NewBooking => 'calendar-plus',
            self::PaymentSubmitted => 'banknote',
            self::ContactMessage => 'mail',
            self::BookingRequest => 'message-square',
        };
    }
}
