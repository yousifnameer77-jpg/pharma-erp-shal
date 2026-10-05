<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Daily database backup at 02:00 (run `php artisan schedule:work` or add the
// standard `schedule:run` cron entry).
\Illuminate\Support\Facades\Schedule::command('backup:database')->dailyAt('02:00');
