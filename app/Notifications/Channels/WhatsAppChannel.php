<?php

namespace App\Notifications\Channels;

use App\Services\Notifications\WhatsApp\WhatsAppDriver;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use Illuminate\Notifications\Notification;

/**
 * The `whatsapp` notification channel (M10.2). A notification on it builds its message in
 * toWhatsApp(); the configured driver sends it or prepares a link.
 */
class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppDriver $driver) {}

    public function send(object $notifiable, Notification $notification): ?string
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return null;
        }

        /** @var WhatsAppMessage $message */
        $message = $notification->toWhatsApp($notifiable);

        return $this->driver->send($message);
    }
}
