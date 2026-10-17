<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Staff account security.
 */
class SecuritySettings extends Settings
{
    // When on, staff must confirm TOTP two-factor authentication before using the admin.
    public bool $require_two_factor;

    public static function group(): string
    {
        return 'security';
    }
}
