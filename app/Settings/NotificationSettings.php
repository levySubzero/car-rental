<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class NotificationSettings extends Settings
{
    /** @var list<string> */
    public array $staff_emails;

    public ?string $staff_whatsapp_number;

    // Event => enabled channels
    /**
     * @phpstan-var array<string, array{mail: bool, whatsapp: bool}>
     */
    public array $channels;

    public string $voucher_email_subject;

    public string $voucher_email_intro;

    // Prefilled text of the voucher WhatsApp message; {reference}, {car} and {dates}.
    public string $voucher_whatsapp_text;

    public static function group(): string
    {
        return 'notification';
    }

    /**
     * @param  'mail'|'whatsapp'  $channel
     */
    public function enabled(string $event, string $channel): bool
    {
        return (bool) ($this->channels[$event][$channel] ?? false);
    }
}
