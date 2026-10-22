<?php

namespace App\Support;

use App\Settings\GeneralSettings;

/**
 * wa.me links with a prefilled message from the WhatsApp templates in General settings
 * (M7.3). resources/js/lib/whatsapp.ts applies the same rules in the browser, so links
 * built on either side match.
 *
 * Templates use {car}, {dates} and {reference}. A placeholder without a value is dropped
 * together with a "for" or "on" in front of it: "rent the {car} for {dates}." becomes
 * "rent the Mazda Demio." when no dates are known.
 */
class WhatsAppLinkBuilder
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function forCar(string $car, ?string $dates = null): ?string
    {
        return $this->link('car_enquiry', ['car' => $car, 'dates' => $dates]);
    }

    public function general(): ?string
    {
        return $this->link('general_enquiry');
    }

    /**
     * Null when no WhatsApp number is set yet.
     *
     * @param  array<string, string|null>  $values
     */
    public function link(string $template, array $values = []): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $this->settings->whatsapp_number);

        if ($number === '') {
            return null;
        }

        $text = self::fill($this->settings->whatsapp_templates[$template] ?? '', $values);

        return "https://wa.me/{$number}".($text === '' ? '' : '?text='.rawurlencode($text));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function fill(string $template, array $values): string
    {
        $text = preg_replace_callback(
            '/(\s+(?:for|on))?\s*\{(\w+)\}/',
            function (array $match) use ($values) {
                $value = trim((string) ($values[$match[2]] ?? ''));

                return $value === '' ? '' : substr($match[0], 0, (int) strpos($match[0], '{')).$value;
            },
            $template,
        );

        return trim((string) preg_replace('/\s{2,}/', ' ', (string) $text));
    }
}
