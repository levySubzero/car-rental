<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Integration keys saved on the admin Integrations page (M12B.15, D46), one encrypted array per
 * integration. Not a SettingsGroup: the generic settings path would send these to the browser
 * and write them to the activity log. Only IntegrationConfig and IntegrationService read them.
 */
class IntegrationSettings extends Settings
{
    /** @phpstan-var array<string, mixed> */
    public array $daraja;

    /** @phpstan-var array<string, mixed> */
    public array $paystack;

    /** @phpstan-var array<string, mixed> */
    public array $resend;

    /** @phpstan-var array<string, mixed> */
    public array $fx;

    /** @phpstan-var array<string, mixed> */
    public array $turnstile;

    /** @phpstan-var array<string, mixed> */
    public array $s3;

    public static function group(): string
    {
        return 'integrations';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['daraja', 'paystack', 'resend', 'fx', 'turnstile', 's3'];
    }
}
