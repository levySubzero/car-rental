<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class RetentionSettings extends Settings
{
    public int $kyc_retention_months;

    public int $draft_retention_days;

    public int $activity_log_retention_months;

    // The Email log (copies of sent emails, bodies included)
    public int $mail_log_retention_days;

    public static function group(): string
    {
        return 'retention';
    }
}
