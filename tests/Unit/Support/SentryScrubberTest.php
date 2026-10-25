<?php

use App\Support\SentryScrubber;
use Sentry\Breadcrumb;
use Sentry\Event;

it('masks booking tokens and the Daraja secret in URLs', function () {
    $token = str_repeat('aB3', 21).'x';

    expect(SentryScrubber::url("https://baines.test/bookings/{$token}/driver?step=2"))
        ->toBe('https://baines.test/bookings/[token]/driver?step=2')
        ->and(SentryScrubber::url('https://baines.test/hooks/mobile/c2b/s3cr3t-value/confirm'))
        ->toBe('https://baines.test/hooks/mobile/c2b/[secret]/confirm')
        ->and(SentryScrubber::url('https://baines.test/hooks/mobile/stk/s3cr3t-value'))
        ->toBe('https://baines.test/hooks/mobile/stk/[secret]')
        ->and(SentryScrubber::url('https://baines.test/fleet'))->toBe('https://baines.test/fleet');
});

it('drops request bodies, cookies and the booking token header from events', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://baines.test/bookings/'.str_repeat('a', 64).'/driver',
        'method' => 'PUT',
        'data' => ['id_number' => '12345678', 'kra_pin' => 'A123456789Z'],
        'cookies' => ['baines_session' => 'x'],
        'headers' => ['X-Booking-Token' => str_repeat('a', 64), 'Accept' => 'text/html'],
    ]);

    $request = SentryScrubber::beforeSend($event)->getRequest();

    expect($request)->not->toHaveKeys(['data', 'cookies'])
        ->and($request['url'])->toBe('https://baines.test/bookings/[token]/driver')
        ->and($request['headers'])->toBe(['Accept' => 'text/html']);
});

it('masks URLs in breadcrumbs', function () {
    $crumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_HTTP, 'http', 'GET /bookings/'.str_repeat('z', 64), [
        'url' => 'https://baines.test/hooks/mobile/stk/secret',
    ]);

    $scrubbed = SentryScrubber::beforeBreadcrumb($crumb);

    expect($scrubbed->getMessage())->toBe('GET /bookings/[token]')
        ->and($scrubbed->getMetadata()['url'])->toBe('https://baines.test/hooks/mobile/stk/[secret]');
});
