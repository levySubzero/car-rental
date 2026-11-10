<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A plain message from the Resend integration page (M12B.14): proves the key, the sending
 * domain and delivery to a real inbox.
 */
class IntegrationTestMail extends Notification
{
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
            ->subject('Test email from '.config('app.name'))
            ->greeting('It works')
            ->line('This test was sent from Admin → Integrations → Resend. If it reached your inbox, not spam, mail is set up correctly.');
    }
}
