<?php

namespace App\Notifications;

use App\Enums\AlertKind;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One in-app alert for staff (M10B), stored on the `database` channel and shown by the bell
 * in the admin top bar. Sent through AdminAlerts, which picks the recipients.
 */
class StaffAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AlertKind $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->kind->value;
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
