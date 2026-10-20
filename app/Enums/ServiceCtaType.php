<?php

namespace App\Enums;

/**
 * What a service card's button does on the public site.
 */
enum ServiceCtaType: string
{
    case Book = 'book';
    case WhatsApp = 'whatsapp';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Book => 'Book a car',
            self::WhatsApp => 'Enquire on WhatsApp',
            self::Link => 'Open a link',
        };
    }
}
