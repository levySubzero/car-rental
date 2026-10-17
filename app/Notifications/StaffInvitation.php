<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a new staff member to set their password. Following the link also verifies the email.
 */
class StaffInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $invitedBy,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $expires = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('You have been invited to the '.config('app.name').' admin')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->invitedBy} has created a staff account for you.")
            ->action('Set your password', $url)
            ->line("This link expires in {$expires} minutes. Ask an administrator to resend it if it does.")
            ->line('After signing in you will be asked to set up two-factor authentication.');
    }
}
