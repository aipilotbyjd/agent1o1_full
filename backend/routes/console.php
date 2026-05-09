<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:snapshot-daily-usage')->dailyAt('00:05');
Schedule::command('billing:expire-credit-packs')->dailyAt('00:10');
Schedule::command('billing:reset-monthly-credits')->daily();

Schedule::command('workflows:schedule-cron')->everyMinute();
Schedule::command('workflows:poll')->everyMinute();
Schedule::command('executions:prune')->dailyAt('02:00');
Schedule::command('executions:archive --batch-size=500')->dailyAt('02:30');
Schedule::command('webhooks:health-check')->dailyAt('03:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('admin:health-check')->everyFiveMinutes();

// Proactively refresh OAuth credentials expiring within 7 days.
// Runs as a queued job on the maintenance queue to avoid blocking the scheduler.
Schedule::job(new \App\Jobs\RefreshOAuthTokenJob())->dailyAt('01:00');
