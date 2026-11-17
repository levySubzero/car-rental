<?php

namespace App\Listeners;

use App\Services\Mail\MailLogService;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Copies every sent email into the Email log. Logging must never break sending.
 */
class LogSentMail
{
    public function __construct(private readonly MailLogService $log) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        if (! $message instanceof Email) {
            return;
        }

        try {
            $this->log->record($message, $event->data);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
