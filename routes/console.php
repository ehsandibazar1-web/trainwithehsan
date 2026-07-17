<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// انتشار خودکار مقالات زمان‌بندی‌شده — هر ۵ دقیقه بررسی می‌شود
Schedule::command('articles:publish-due')->everyFiveMinutes();

// اعلان مهلت‌های نزدیک برنامه‌ریز محتوا — هر ساعت کافی است (بر خلاف انتشار، دقتِ دقیقه‌ای لازم نیست)
Schedule::command('content-plans:notify-deadlines')->hourly();

// ممیزی هفتگیِ خودکار AI Agent — نگاه کنید به App\Services\AiAgent\AgentAuditService
Schedule::command('agent:audit')->weekly();
