<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Page;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // نام کوتاه به‌جای FQCN در ستون‌های چندریختی (keywords.keywordable_type و
        // internal_link_suggestions.source_type/target_type) — همان قرارداد رشته‌های کوتاه
        // ('Article'/'Page') که در سراسر SeoAuditService/MediaUsageScanner استفاده می‌شود
        Relation::morphMap([
            'Article' => Article::class,
            'Page' => Page::class,
        ]);

        // محدودیت نرخ فرم خبرنامه — جلوی ثبت‌نام انبوه/اسپم را می‌گیرد
        // (۵ درخواست در دقیقه و ۲۰ در ساعت به‌ازای هر IP)
        RateLimiter::for('newsletter', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        // محدودیت نرخ API ایمپورت هوش مصنوعی — به‌ازای هر توکن (و اگر نبود، IP)
        RateLimiter::for('ai-import-api', function (Request $request) {
            $key = $request->attributes->get('ai_api_token')?->id ?? $request->ip();

            return [
                Limit::perMinute(30)->by($key),
                Limit::perHour(300)->by($key),
            ];
        });
    }
}
