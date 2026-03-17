<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// ETF: even minutes (0,2,4...58)
Schedule::command('app:sync-realtime-prices', ['type' => 'etf'])
    ->everyMinute()
    ->weekdays()
    ->between('9:30', '11:31')
    ->when(fn () => (int) now()->format('i') % 2 === 0);

Schedule::command('app:sync-realtime-prices', ['type' => 'etf'])
    ->everyMinute()
    ->weekdays()
    ->between('13:00', '15:01')
    ->when(fn () => (int) now()->format('i') % 2 === 0);

// Index: minutes 1,11,21,31,41,51
Schedule::command('app:sync-realtime-prices', ['type' => 'index'])
    ->weekdays()
    ->cron('1,11,21,31,41,51 9-11 * * 1-5')
    ->between('9:30', '11:31');

Schedule::command('app:sync-realtime-prices', ['type' => 'index'])
    ->weekdays()
    ->cron('1,11,21,31,41,51 13-15 * * 1-5')
    ->between('13:00', '15:01');

Schedule::command('app:sync-realtime-prices', ['type' => 'index'])
    ->weekdays()
    ->at('17:01');

Schedule::command('app:check-price-alerts')
    ->everyMinute()
    ->weekdays()
    ->between('9:30', '11:31');

Schedule::command('app:check-price-alerts')
    ->everyMinute()
    ->weekdays()
    ->between('13:00', '15:01');
