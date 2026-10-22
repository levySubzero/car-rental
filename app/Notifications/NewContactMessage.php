<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff a renter used the contact form (M7.15). Sent to Notification settings'
 * staff emails; the message itself is in Admin → Contact messages.
 */
class NewContactMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactMessage $contactMessage)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->contactMessage->loadMissing('car');

        $mail = (new MailMessage)
            ->subject("New enquiry from {$message->name}")
            ->greeting('New website enquiry')
            ->line("**From:** {$message->name}");

        foreach (array_filter([
            'Email' => $message->email,
            'Phone' => $message->phone,
            'Car' => $message->car?->name,
            'Dates' => $message->preferred_dates,
        ]) as $label => $value) {
            $mail->line("**{$label}:** {$value}");
        }

        return $mail
            ->line($message->message)
            ->action('Open in the admin', route('admin.contact-messages.show', $message));
    }
}
