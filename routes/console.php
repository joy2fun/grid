<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:sync-realtime-prices')
    ->everyMinute()
    ->weekdays()
    ->between('9:30', '11:30');

Schedule::command('app:sync-realtime-prices')
    ->everyMinute()
    ->weekdays()
    ->between('13:00', '15:00');
