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
        // Hard ceiling for any media collection, in kilobytes. Room for the hero video;
        // images stay at 8 MB per collection. PHP's upload_max_filesize and post_max_size
        // must be at least this large on the server.
        'max_kb_ceiling' => 20480,
    ],

    // The business's local time zone. Billable days are counted in it (D30); the app and
    // the database stay in UTC.
    'timezone' => 'Africa/Nairobi',

    // Admin sessions are logged out after this many minutes without a request.
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    // Who hears about operational trouble: Horizon's long-wait and failure mails (M12.10).
    'ops_alert_email' => env('OPS_ALERT_EMAIL'),

    // Where browsers report CSP violations (a Sentry security endpoint), or null (M12.3).
    'csp_report_uri' => env('CSP_REPORT_URI'),

    // Hosts without a long-running worker (cPanel, D44): the scheduler drains the
    // database queue every minute instead of Horizon.
    'queue_via_scheduler' => (bool) env('QUEUE_VIA_SCHEDULER', false),

];
