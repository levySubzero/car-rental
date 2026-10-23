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
        return self::linkTo(
            $this->settings->whatsapp_number,
            self::fill($this->settings->whatsapp_templates[$template] ?? '', $values),
        );
    }

    /**
     * A chat with any number, e.g. a renter's phone (M10). Null when the number has no digits.
     * Numbers written locally ("0712 345 678") are taken as Kenyan.
     */
    public static function linkTo(?string $number, string $text = ''): ?string
    {
        $number = (string) preg_replace('/\D+/', '', (string) $number);

        if ($number === '') {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '254'.substr($number, 1);
        }

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
