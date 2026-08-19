<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auctions:finish-expired')
    ->everyMinute()
    ->withoutOverlapping(5);
Schedule::command('activities:notify-upcoming')->everyMinute()->withoutOverlapping(5);
