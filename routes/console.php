<?php

use App\Services\Booking\KycPurgeService;
use App\Services\Notifications\AdminAlerts;
use App\Services\Payments\Gateways\GatewayException;
use App\Services\Payments\OnlinePaymentService;
use App\Settings\RetentionSettings;
use Carbon\CarbonImmutable;
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

// Read staff alerts are kept for 90 days (M10B).
Artisan::command('alerts:prune {--days=90}', function (AdminAlerts $alerts) {
    $this->info($alerts->prune((int) $this->option('days')).' read alerts deleted.');
})->purpose('Delete read staff alerts older than --days');
Schedule::command('alerts:prune')->daily()->onOneServer();

// Retention (M11.11): KYC some months after a booking ends (unless on legal hold), and never
// submitted drafts, per RetentionSettings.
Artisan::command('kyc:purge', function (KycPurgeService $purge) {
    $counts = $purge->purge();
    $this->info("KYC purged from {$counts['bookings']} bookings; {$counts['drafts']} old drafts deleted.");
})->purpose('Delete KYC past its retention period and old drafts');
Schedule::command('kyc:purge')->dailyAt('02:30')->withoutOverlapping()->onOneServer();

// The activity log keeps RetentionSettings::activity_log_retention_months, read when it runs.
Artisan::command('activity:prune', function (RetentionSettings $retention) {
    $days = (int) CarbonImmutable::now()->subMonths($retention->activity_log_retention_months)->diffInDays(CarbonImmutable::now());

    return $this->call('activitylog:clean', ['--days' => $days, '--force' => true]);
})->purpose('Delete activity older than the retention setting');
Schedule::command('activity:prune')->weekly()->onOneServer();

// Online payments (M11B.6): STK pushes and card payments the gateway never reported back on.
Artisan::command('payments:reconcile', function (OnlinePaymentService $payments) {
    $counts = $payments->reconcile();
    $this->info("{$counts['checked']} checked: {$counts['paid']} paid, {$counts['failed']} failed, {$counts['errors']} could not be asked.");
})->purpose('Ask the gateways about online payments still waiting for an answer');
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Once per environment, after the Daraja keys are set: Safaricom then reports paybill payments.
Artisan::command('mpesa:register-c2b', function (OnlinePaymentService $payments) {
    try {
        $payments->registerC2b();
    } catch (GatewayException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info('C2B confirmation and validation URLs registered with Safaricom.');

    return 0;
})->purpose('Register the paybill (C2B) callback URLs with Safaricom');
