<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler (TECHNICAL_PLAN §17). Other jobs are added by their milestones.
Schedule::command('media:cleanup')->hourly()->withoutOverlapping()->onOneServer();
