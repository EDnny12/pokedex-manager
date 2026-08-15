<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:prune-database-state')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

Schedule::command(sprintf(
    'db:monitor --databases=%s --max=%d',
    config('database.default'),
    (int) config('database.monitoring.max_connections', 80),
))
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);
