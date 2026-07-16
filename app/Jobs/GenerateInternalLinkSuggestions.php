<?php

namespace App\Jobs;

use App\Services\InternalLinking\SuggestionEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * بازتولید صف‌شده‌ی پیشنهادهای لینک داخلی — روی همه‌ی مقاله/صفحه‌ها (O(n²) امتیازدهی) اجرا
 * می‌شود، پس به‌جای بلاک‌کردن درخواست ادمین در صف اجرا می‌شود («use queues where appropriate»).
 * تصمیم‌های قبلی ادمین (approved/dismissed) دست‌نخورده می‌مانند — نگاه کنید به
 * SuggestionEngine::generateAndPersist().
 */
class GenerateInternalLinkSuggestions implements ShouldQueue
{
    use Queueable;

    public function handle(SuggestionEngine $engine): void
    {
        $engine->generateAndPersist();
    }
}
