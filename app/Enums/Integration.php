<?php

namespace App\Enums;

use App\Services\Integrations\IntegrationField;

/**
 * The third-party services whose keys can be saved on the admin Integrations page (M12B.15,
 * D46). A saved value overrides the config path it maps to; without one, env applies. Values
 * read at boot or built into assets (APP_KEY, the database, Sentry, VITE_*) stay env-only.
 */
enum Integration: string
{
    case Daraja = 'daraja';
    case Paystack = 'paystack';
    case Resend = 'resend';
    case Fx = 'fx';
    case Turnstile = 'turnstile';
    case S3 = 's3';

    public function label(): string
    {
        return match ($this) {
            self::Daraja => 'M-Pesa (Daraja)',
            self::Paystack => 'Paystack',
            self::Resend => 'Mail (Resend)',
            self::Fx => 'Exchange rates',
            self::Turnstile => 'Turnstile',
            self::S3 => 'Media storage (S3)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Daraja => 'Safaricom keys for M-Pesa prompts and paybill or till payments.',
            self::Paystack => 'Card payments, and M-Pesa when it is set to go through Paystack.',
            self::Resend => 'How the app sends email, and who it comes from.',
            self::Fx => 'The Open Exchange Rates key. The provider itself is chosen under Settings, Exchange rates.',
            self::Turnstile => 'The Cloudflare challenge on the public booking and contact forms.',
            self::S3 => 'Where new uploads are stored. Existing files stay on the disk they were written to.',
        };
    }

    /**
     * @return list<IntegrationField>
     */
    public function fields(): array
    {
        $secret = IntegrationFieldType::Secret;
        $text = IntegrationFieldType::Text;
        $choice = IntegrationFieldType::Choice;

        return match ($this) {
            self::Daraja => [
                new IntegrationField('env', 'Environment', $choice, ['services.daraja.env'], ['sandbox' => 'Sandbox', 'production' => 'Production']),
                new IntegrationField('type', 'Account type', $choice, ['services.daraja.type'], ['paybill' => 'Paybill', 'till' => 'Till']),
                new IntegrationField('consumer_key', 'Consumer key', $secret, ['services.daraja.consumer_key']),
                new IntegrationField('consumer_secret', 'Consumer secret', $secret, ['services.daraja.consumer_secret']),
                new IntegrationField('shortcode', 'Shortcode', $text, ['services.daraja.shortcode'], description: 'The paybill, or the store (head office) number for a till. A new one needs mpesa:register-c2b again.'),
                new IntegrationField('till_number', 'Till number', $text, ['services.daraja.till_number'], description: 'Only for a till.'),
                new IntegrationField('passkey', 'Passkey', $secret, ['services.daraja.passkey']),
                new IntegrationField('callback_secret', 'Callback secret', $secret, ['services.daraja.callback_secret'], description: 'A long random string in the callback URLs. A new one needs mpesa:register-c2b again, and production allows that once per shortcode.'),
                new IntegrationField('allowed_ips', 'Allowed IPs', IntegrationFieldType::List, ['services.daraja.allowed_ips'], description: "Safaricom's addresses or CIDR ranges, comma separated. Required in production."),
            ],
            self::Paystack => [
                new IntegrationField('public_key', 'Public key', $text, ['services.paystack.public_key']),
                new IntegrationField('secret_key', 'Secret key', $secret, ['services.paystack.secret_key'], description: 'Test or live follows the keys: both must be pk_test_ and sk_test_, or both pk_live_ and sk_live_.'),
                new IntegrationField('mpesa_provider', 'Take M-Pesa through', $choice, ['services.mpesa.provider'], self::mpesaProviders(), 'With Paystack, M-Pesa needs mobile money enabled on the Paystack account, and the pay-by-paybill line leaves the booking page.'),
            ],
            self::Resend => [
                new IntegrationField('mailer', 'Send mail with', $choice, ['mail.default'], ['log' => 'Log only (nothing is sent)', 'resend' => 'Resend']),
                new IntegrationField('key', 'API key', $secret, ['services.resend.key']),
                new IntegrationField('from_address', 'From address', $text, ['mail.from.address'], rules: ['email']),
                new IntegrationField('from_name', 'From name', $text, ['mail.from.name']),
            ],
            self::Fx => [
                new IntegrationField('api_key', 'API key', $secret, ['services.fx.api_key']),
            ],
            self::Turnstile => [
                new IntegrationField('site_key', 'Site key', $text, ['services.turnstile.site_key']),
                new IntegrationField('secret_key', 'Secret key', $secret, ['services.turnstile.secret_key']),
            ],
            self::S3 => [
                new IntegrationField('public_disk', 'Public files disk', $choice, ['media.disks.public'], ['media-public-local' => 'This server', 'media-public-s3' => 'S3']),
                new IntegrationField('private_disk', 'Private files disk', $choice, ['media.disks.private'], ['media-private-local' => 'This server', 'media-private-s3' => 'S3']),
                new IntegrationField('key', 'Access key ID', $secret, ['filesystems.disks.media-public-s3.key', 'filesystems.disks.media-private-s3.key']),
                new IntegrationField('secret', 'Secret access key', $secret, ['filesystems.disks.media-public-s3.secret', 'filesystems.disks.media-private-s3.secret']),
                new IntegrationField('region', 'Region', $text, ['filesystems.disks.media-public-s3.region', 'filesystems.disks.media-private-s3.region']),
                new IntegrationField('public_bucket', 'Public bucket', $text, ['filesystems.disks.media-public-s3.bucket']),
                new IntegrationField('private_bucket', 'Private bucket', $text, ['filesystems.disks.media-private-s3.bucket']),
                new IntegrationField('url', 'Public URL', $text, ['filesystems.disks.media-public-s3.url'], description: 'A CDN or bucket URL. Optional.', rules: ['url']),
                new IntegrationField('endpoint', 'Endpoint', $text, ['filesystems.disks.media-public-s3.endpoint', 'filesystems.disks.media-private-s3.endpoint'], description: 'Only for an S3-compatible store.', rules: ['url']),
                new IntegrationField('path_style', 'Use path-style URLs', IntegrationFieldType::Toggle, ['filesystems.disks.media-public-s3.use_path_style_endpoint', 'filesystems.disks.media-private-s3.use_path_style_endpoint'], description: 'Only if an S3-compatible store asks for it.'),
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private static function mpesaProviders(): array
    {
        $options = [];

        foreach (MpesaProvider::cases() as $provider) {
            $options[$provider->value] = $provider->label();
        }

        return $options;
    }
}
