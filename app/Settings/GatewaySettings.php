<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * What the system records about the payment gateways (M11B.6), not edited by staff: when the
 * C2B URLs were registered with Safaricom, and for which shortcode, so a new shortcode shows
 * as needing registration again.
 */
class GatewaySettings extends Settings
{
    public ?string $c2b_registered_at;

    public ?string $c2b_registered_shortcode;

    public static function group(): string
    {
        return 'gateways';
    }
}
