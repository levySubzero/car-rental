<?php

namespace App\Enums;

/**
 * How a field on the admin Integrations page is entered and stored.
 */
enum IntegrationFieldType: string
{
    // Never sent back to the browser; a blank input keeps the current value.
    case Secret = 'secret';
    case Text = 'text';
    case Choice = 'choice';
    // Typed comma-separated, stored as an array.
    case List = 'list';
    case Toggle = 'toggle';
}
