<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code for the renter portal (M10B). Sent straight away, not queued: the renter
 * is waiting for it on the page.
 */
class PortalCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly int $minutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your code to see your bookings: {$this->code}")
            ->greeting('Your sign-in code')
            ->line("Enter this code to see your bookings: **{$this->code}**")
            ->line("It works once and expires in {$this->minutes} minutes.")
            ->line('If you did not ask for it, you can ignore this email.');
    }
}
