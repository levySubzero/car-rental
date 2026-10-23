<?php

namespace App\Services\Notifications\WhatsApp;

use Illuminate\Database\Eloquent\Model;

/**
 * One WhatsApp message a notification wants sent (M10.2). `subject` is the record the
 * activity log ties it to, usually the booking.
 */
final readonly class WhatsAppMessage
{
    public function __construct(
        public ?string $to,
        public string $text,
        public string $notification,
        public ?Model $subject = null,
    ) {}
}
