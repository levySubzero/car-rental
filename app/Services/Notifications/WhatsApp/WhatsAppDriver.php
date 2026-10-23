<?php

namespace App\Services\Notifications\WhatsApp;

/**
 * How WhatsApp messages leave the app (TECHNICAL_PLAN §13), chosen by
 * `services.whatsapp.driver`.
 */
interface WhatsAppDriver
{
    /**
     * Send the message, or prepare it for staff to send. Returns the wa.me link when the
     * driver only builds one.
     */
    public function send(WhatsAppMessage $message): ?string;
}
