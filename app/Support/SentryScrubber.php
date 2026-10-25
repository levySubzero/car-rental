<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

/**
 * Keeps renter secrets and identity data out of Sentry (M12.4). Wired in config/sentry.php.
 *
 * send_default_pii already drops cookies, the IP and auth headers. What is left to remove:
 * request bodies (the driver step carries ID, KRA PIN and licence numbers, gateway callbacks
 * carry phones), the renter's booking token in URLs and its header, and the Daraja callback
 * secret in the hooks path.
 */
final class SentryScrubber
{
    private const MASKS = [
        '#/bookings/[A-Za-z0-9]{20,}#' => '/bookings/[token]',
        '#/hooks/mobile/(stk|c2b)/[^/?\s]+#' => '/hooks/mobile/$1/[secret]',
    ];

    private const HEADERS = ['x-booking-token', 'x-paystack-signature', 'x-xsrf-token', 'x-csrf-token'];

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        $request = $event->getRequest();

        if ($request !== []) {
            unset($request['data'], $request['cookies']);

            if (isset($request['url']) && is_string($request['url'])) {
                $request['url'] = self::url($request['url']);
            }

            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = array_filter(
                    $request['headers'],
                    fn ($name) => ! in_array(strtolower((string) $name), self::HEADERS, true),
                    ARRAY_FILTER_USE_KEY,
                );

                if (isset($request['headers']['Referer']) && is_string($request['headers']['Referer'])) {
                    $request['headers']['Referer'] = self::url($request['headers']['Referer']);
                }
            }

            $event->setRequest($request);
        }

        return $event;
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $message = $breadcrumb->getMessage();

        if ($message !== null) {
            $breadcrumb = $breadcrumb->withMessage(self::url($message));
        }

        foreach ($breadcrumb->getMetadata() as $key => $value) {
            if (is_string($value)) {
                $breadcrumb = $breadcrumb->withMetadata($key, self::url($value));
            }
        }

        return $breadcrumb;
    }

    public static function url(string $value): string
    {
        return (string) preg_replace(array_keys(self::MASKS), array_values(self::MASKS), $value);
    }
}
