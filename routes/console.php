<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler (TECHNICAL_PLAN §17). Other jobs are added by their milestones.
Schedule::command('media:cleanup')->hourly()->withoutOverlapping()->onOneServer();

// The command itself waits FxSettings::refresh_minutes between fetches and does nothing
// while the provider is manual.
Schedule::command('fx:refresh')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

// Drafts lapse after PricingSettings::draft_expiry_hours, unpaid bookings when their hold ends.
Schedule::command('bookings:expire')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
