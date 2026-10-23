<?php

namespace App\Listeners;

use App\Notifications\Booking\BookingNotification;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Every booking notification that left is logged on its booking (M10.10). Recipients are
 * not stored: the log says which message went out and on which channel.
 */
class LogNotificationSent
{
    public const LOG_NAME = 'notifications';

    public function handle(NotificationSent $event): void
    {
        $notification = $event->notification;

        if (! $notification instanceof BookingNotification) {
            return;
        }

        activity(self::LOG_NAME)->performedOn($notification->booking)
            ->withProperties([
                'notification' => $notification->event(),
                'channel' => $event->channel === WhatsAppChannel::class ? 'whatsapp' : $event->channel,
            ])
            ->event('sent')
            ->log($notification->event().' sent');
    }
}
