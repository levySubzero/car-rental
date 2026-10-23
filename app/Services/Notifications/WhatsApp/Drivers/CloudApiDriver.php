<?php

namespace App\Services\Notifications\WhatsApp\Drivers;

use App\Services\Notifications\WhatsApp\WhatsAppDriver;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use LogicException;

/**
 * WhatsApp Cloud API with approved templates (P2.3). Not built yet: selecting it fails
 * loudly instead of dropping messages.
 */
class CloudApiDriver implements WhatsAppDriver
{
    public function send(WhatsAppMessage $message): ?string
    {
        throw new LogicException('The WhatsApp Cloud API driver is not available yet (P2.3). Set WHATSAPP_DRIVER=click_to_chat.');
    }
}
