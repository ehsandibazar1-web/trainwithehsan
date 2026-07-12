<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// انتشار خودکار مقالات زمان‌بندی‌شده — هر ۵ دقیقه بررسی می‌شود
Schedule::command('articles:publish-due')->everyFiveMinutes();
