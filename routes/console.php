<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull new punches off the devices unattended. withoutOverlapping stops a
// slow or unreachable device from stacking up overlapping sync runs.
Schedule::command('attendance:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Safety net: re-queue any attendance records that are still unpushed
// (e.g. the queued job exhausted its retries during a long outage).
// The job is unique, so overlapping dispatches are ignored.
Schedule::command('attendance:push')->everyFiveMinutes();
