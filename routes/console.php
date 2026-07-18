<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (env('BACKUP_SCHEDULE_ENABLED', false)) {
    Schedule::command('system:backup')->dailyAt(env('BACKUP_SCHEDULE_TIME', '02:00'))->withoutOverlapping();
}
