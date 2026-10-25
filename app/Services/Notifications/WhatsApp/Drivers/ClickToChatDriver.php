<?php

namespace App\Services\Notifications\WhatsApp\Drivers;

use App\Services\Notifications\WhatsApp\WhatsAppDriver;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use App\Support\WhatsAppLinkBuilder;

/**
 * v1 driver: nothing is sent. The wa.me link with the prefilled text is returned for staff
 * to open (the verify toast, the booking page, M11.5). The activity row records only that
 * it was built: the link holds the phone number and the message (M12.4).
 */
class ClickToChatDriver implements WhatsAppDriver
{
    public const LOG_NAME = 'whatsapp';

    public function send(WhatsAppMessage $message): ?string
    {
        $link = WhatsAppLinkBuilder::linkTo($message->to, $message->text);

        activity(self::LOG_NAME)
            ->when($message->subject !== null, fn ($log) => $log->performedOn($message->subject))
            ->withProperties(['notification' => $message->notification])
            ->event($link === null ? 'no-number' : 'link-built')
            ->log($link === null ? 'no WhatsApp number to send to' : 'WhatsApp link ready to send');

        return $link;
    }
}
