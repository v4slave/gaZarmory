<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auctions:finish-expired')
    ->everyMinute()
    ->withoutOverlapping(5);
