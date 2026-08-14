<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\ContentPlan;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Models\Page;
use App\Services\AiAssistant\Contracts\AiProvider;
use App\Services\AiAssistant\Providers\AnthropicProvider;
use App\Services\AiAssistant\Providers\NullProvider;
use App\Services\Rag\Contracts\VectorStore;
use App\Services\Rag\VectorStores\EloquentCosineVectorStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // این بایندینگ دیگر محلِ افزودنِ ارائه‌دهنده‌ی جدید نیست — از وقتی ProviderManager اضافه شد
        // (Section 24 در CLAUDE.md)، یک ارائه‌دهنده‌ی تازه یک کلاسِ AiProvider جدید + یک ردیف در
        // ProviderManager::DRIVERS است، نه یک شاخه‌ی دیگر اینجا. این بایندینگ فقط مسیرِ پشتیبانِ
        // دائمیِ .env-only است (ANTHROPIC_API_KEY، وقتی هیچ‌چیزی در دیتابیس تنظیم نشده) — طبق
        // «Things That Must Never Be Changed» عمداً دست‌نخورده و تک‌وندوری باقی می‌ماند
        $this->app->bind(AiProvider::class, function () {
            if (blank(config('services.anthropic.key'))) {
                return new NullProvider;
            }

            return match (config('services.anthropic.driver', 'anthropic')) {
                default => new AnthropicProvider,
            };
        });

        // انتخاب پیاده‌سازیِ VectorStore — تنها جایی که مهاجرت به یک پایگاه‌داده‌ی برداریِ دیگر
        // (مثلاً pgvector) لازم است تغییر کند؛ نگاه کنید به App\Services\Rag\Contracts\VectorStore
        $this->app->bind(VectorStore::class, EloquentCosineVectorStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ثابت‌کردنِ ریشه‌ی URLِ تولیدشده روی همان میزبانِ کانونیکالِ APP_URL — بدونِ این،
        // url('/path')/url()->current() (که canonical/og:url/hreflang/sitemap/RSS در سراسرِ
        // این پروژه از همان‌ها می‌سازند) از هاستِ *درخواستِ فعلی* پیروی می‌کنند، نه از تنظیمات؛
        // یعنی یک بازدیدکننده/کراولر که از www یا http به سایت می‌رسید، همان canonical/og:url
        // نادرست (با همان هاستِ غیرکانونیکال) می‌گرفت — دقیقاً همان چیزی که ممیزیِ GSC (کنسولِ
        // جست‌وجوی گوگل) به‌عنوانِ «۴ نسخه‌ی جداگانه ایندکس شده» گزارش کرد. تأییدشده با تستِ
        // مستقیم روی UrlGenerator قبل از این فیکس. ریدایرکتِ ۳۰۱ در public/.htaccess کاری می‌کند
        // که بازدیدکننده‌ی واقعی اصلاً به هاستِ غیرکانونیکال نرسد؛ این خط لایه‌ی دومِ دفاع است —
        // حتی اگر یک درخواست به هر دلیلی روی هاستِ غیرکانونیکال به Laravel برسد، هر URLای که
        // خودِ اپ می‌سازد باز هم درست (APP_URL) می‌ماند، نه بازتابِ آن درخواست. کاملاً محیط‌محور
        // است (نه یک دامنه‌ی هاردکد) — local/staging همچنان APP_URLِ خودشان را می‌گیرند.
        URL::forceRootUrl(config('app.url'));

        // نام کوتاه به‌جای FQCN در ستون‌های چندریختی (keywords.keywordable_type و
        // internal_link_suggestions.source_type/target_type) — همان قرارداد رشته‌های کوتاه
        // ('Article'/'Page') که در سراسر SeoAuditService/MediaUsageScanner استفاده می‌شود
        Relation::morphMap([
            'Article' => Article::class,
            'Page' => Page::class,
            'ContentPlan' => ContentPlan::class,
            'KnowledgeEntry' => KnowledgeEntry::class,
            'KnowledgeEntryAttachment' => KnowledgeEntryAttachment::class,
        ]);

        // محدودیت نرخ فرم خبرنامه — جلوی ثبت‌نام انبوه/اسپم را می‌گیرد
        // (۵ درخواست در دقیقه و ۲۰ در ساعت به‌ازای هر IP)
        RateLimiter::for('newsletter', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        // محدودیت نرخ فرم تماس — همون سقفِ فرم خبرنامه (۵ در دقیقه، ۲۰ در ساعت به‌ازای هر IP)
        RateLimiter::for('contact', function (Request $request) {
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

        // سقفِ جداگانه و صرفاً بر اساس IP که *قبل* از بررسیِ توکن اجرا می‌شود — چون میان‌افزار
        // احراز هویت روی توکنِ نامعتبر/گم‌شده کوتاه‌مدار می‌شود، بدون این سقف درخواست‌های
        // ناموفق هیچ محدودیتی نداشتند. سقف عمداً بالاتر از ۳۰/دقیقه است تا رفتار موجودِ توکنِ
        // معتبر (که با ai-import-api کنترل می‌شود) دست‌نخورده بماند؛ فقط جلوی سیل درخواست با
        // توکن نامعتبر/گم‌شده را می‌گیرد
        RateLimiter::for('ai-import-auth', function (Request $request) {
            return [
                Limit::perMinute(60)->by($request->ip()),
            ];
        });
    }
}
