<?php

/*
|--------------------------------------------------------------------------
| Baines application constants
|--------------------------------------------------------------------------
|
| Values that are fixed by the code rather than tuned by staff. Anything
| the client may change after launch belongs in a settings class under
| app/Settings instead (TECHNICAL_PLAN §9).
|
*/

return [

    // Quick sign-in dropdown on the login page. Only ever active when APP_ENV=local.
    'dev_login' => (bool) env('DEV_LOGIN', true),

    // The one system role. It is seeded and locked, and its holders pass every
    // permission check (Gate::before). All other roles are managed in the admin.
    'super_admin_role' => 'super-admin',

    'booking_reference' => [
        'prefix' => 'BE',
        'format' => '{prefix}-{year}-{seq}',
        'sequence_padding' => 6,
    ],

    // Length of the plain renter access token; only its SHA-256 hash is stored.
    'booking_token_length' => 64,

    'uploads' => [
        // Hard ceiling for any media collection, in kilobytes.
        'max_kb_ceiling' => 8192,
    ],

    // Currency codes accepted on per-car amounts such as the damage excess.
    // M6 replaces this with the currencies table.
    'currencies' => ['KES', 'USD', 'GBP', 'EUR', 'AED'],

    // Admin sessions are logged out after this many minutes without a request.
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

];
