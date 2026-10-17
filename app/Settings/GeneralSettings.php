<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Company profile, contacts, location, socials and WhatsApp message templates.
 * Values marked TODO in the seed pack are null until the client supplies them.
 */
class GeneralSettings extends Settings
{
    public string $company_name;

    public ?string $legal_name;

    /** @var list<string> */
    public array $phones;

    public ?string $whatsapp_number;

    public ?string $email;

    public ?string $address;

    public ?string $map_embed_url;

    public ?float $latitude;

    public ?float $longitude;

    // Platform => URL
    /** @var array<string, string> */
    public array $socials;

    /**
     * @phpstan-var list<array{days: string, hours: string}>
     */
    public array $opening_hours;

    // Template key => text with {car}, {dates} and {reference} placeholders
    /** @var array<string, string> */
    public array $whatsapp_templates;

    public static function group(): string
    {
        return 'general';
    }
}
