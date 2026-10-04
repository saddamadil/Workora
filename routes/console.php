<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs one cron line on the server: * * * * * php /path/to/artisan schedule:run
Illuminate\Support\Facades\Schedule::command('reminders:due')->dailyAt('08:00');
