<?php

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;

// Staging and production send through Resend (M11B.13): MAIL_MAILER=resend and RESEND_API_KEY.
it('builds the Resend transport from the API key', function () {
    config(['services.resend.key' => 're_test_123', 'mail.default' => 'resend']);

    expect(Mail::mailer()->getSymfonyTransport())->toBeInstanceOf(ResendTransport::class)
        ->and((string) Mail::mailer()->getSymfonyTransport())->toBe('resend');
});

it('keeps local and test mail off Resend by default', function () {
    expect(config('mail.default'))->not->toBe('resend');
});
