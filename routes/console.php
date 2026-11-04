<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('clinic:prune-expired-schedules')->dailyAt('00:10')->withoutOverlapping();
