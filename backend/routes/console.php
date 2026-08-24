<?php

use App\Console\Commands\AutoUnlockFeederSurveys;
use App\Console\Commands\CleanupStaleChunks;
use App\Console\Commands\ProcessPendingUploads;
use App\Console\Commands\RemindOpenAssignments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production: schedule cron `* * * * * php artisan schedule:run`
// Local XAMPP: on-read unlock in FeederSurvey still clears expired locks without cron.
Schedule::command(AutoUnlockFeederSurveys::class)->daily();
Schedule::command(RemindOpenAssignments::class)->dailyAt('09:00');

// Large uploads are parsed in the background. There is no queue worker on cPanel
// (QUEUE_CONNECTION=sync), so the every-minute cron is what actually drives them.
Schedule::command(ProcessPendingUploads::class)->everyMinute()->withoutOverlapping();
Schedule::command(CleanupStaleChunks::class)->dailyAt('02:30');
