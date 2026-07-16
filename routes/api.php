<?php

use App\Http\Controllers\Api\AiImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ایمپورت هوش مصنوعی — تنها سطح API این پروژه
|--------------------------------------------------------------------------
| احراز هویت: Bearer token (پنل → AI Studio → API Tokens).
| سیاست: هرگز مستقیم منتشر نمی‌کند — همیشه پیش‌نویس؛ انتشار فقط از
| گردش‌کار موجود پنل (Draft Queue → تأیید مدیر) انجام می‌شود.
*/

Route::middleware(['ai.import.token', 'throttle:ai-import-api'])->group(function () {
    Route::post('/ai-import/validate', [AiImportController::class, 'validatePayload']);
    Route::post('/ai-import', [AiImportController::class, 'store']);
});
