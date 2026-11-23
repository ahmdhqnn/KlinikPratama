<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('clinic:prune-expired-schedules')->dailyAt('00:10')->withoutOverlapping();
if (config('clinical_backup.enabled')) {
    Schedule::command('clinic:backup')->dailyAt('01:00')->timezone('Asia/Jakarta')->withoutOverlapping();
}
