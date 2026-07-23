<?php

namespace App\Http\Middleware;

use App\Http\Controllers\BlogController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoController;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * کشِ کاملِ HTML روی لبه‌ی Cloudflare — بزرگ‌ترین اهرمِ کاهشِ TTFB (سِروِ HTML از لبه به‌جای رفتن
 * تا هاست). این میدلور فقط «مجاز و امن بودنِ کش» را به Cloudflare اعلام می‌کند؛ خودِ کش‌کردن با
 * یک Cache Rule در پنلِ Cloudflare فعال می‌شود (Cache Everything + Respect origin TTL).
 *
 * سه گاردِ ایمنی، تا فرم‌ها/سشن/ادمین هرگز نشکنند:
 *   1) فقط روی صفحاتِ عمومیِ بی‌عارضه‌ی GET (allowlistِ صریحِ اکشن‌ها) عمل می‌کند — نه POST،
 *      نه ادمین، نه لینک‌های توکن‌دارِ خبرنامه، نه پیش‌نمایشِ امضاشده.
 *   2) فقط برای بازدیدکننده‌ی «ناشناس» (بدونِ کوکیِ سشن). هرکس سشن دارد (ادمینِ لاگین‌شده یا
 *      کاربری که وسطِ تعامل است) پاسخِ خصوصی می‌گیرد و اصلاً کش نمی‌شود.
 *   3) برای همان بازدیدکننده‌ی ناشناس، کوکیِ سشن/XSRF از پاسخ حذف می‌شود تا نسخه‌ی کش‌شده کاملاً
 *      «بی‌کاربر» باشد و کوکیِ یک نفر روی بقیه نشت نکند (Cloudflare پاسخِ Set-Cookie‌دار را هم
 *      به‌صورت پیش‌فرض کش نمی‌کند). توکنِ CSRFِ داخلِ متا در نسخه‌ی کش‌شده مشترک/کهنه می‌شود؛ فرم‌ها
 *      پیش از ارسال از /csrf-token توکنِ تازه می‌گیرند، پس چیزی نمی‌شکند.
 *
 * پیش‌فرض خاموش است: تا وقتی کلیدِ edge_cache.enabled در تنظیمات روشن نشود، این میدلور هیچ‌کاری
 * نمی‌کند و رفتارِ سایت مو‌به‌مو مثلِ امروز است. کلیدِ خاموش‌کردنِ سریع در صفحه‌ی System Maintenance
 * است (برای مواقعی که چیزی مشکوک دیده شد — بدون نیاز به دیپلوی).
 *
 * ترتیبِ اجرا مهم است: این میدلور با web(prepend: ...) پیش از StartSession ثبت می‌شود، پس در فازِ
 * پاسخ «بیرونی‌تر» از StartSession است و کوکیِ سشنی که StartSession اضافه کرده را می‌بیند و می‌تواند
 * حذف کند. اگر بعد از StartSession اجرا می‌شد، هنوز کوکی اضافه نشده بود و حذف بی‌اثر می‌ماند.
 */
class EdgeCache
{
    // اکشن‌های عمومیِ کش‌پذیر — دقیقاً صفحاتِ محتوایی که هیچ حالتِ کاربر/فلش/توکنِ خاصی ندارند.
    // هرچه اینجا نباشد (POST، ادمین، لایوایر، verify/unsubscribeِ توکن‌دار، preview امضاشده) کش نمی‌شود.
    private const CACHEABLE_ACTIONS = [
        BlogController::class.'@home',
        BlogController::class.'@homeTr',
        BlogController::class.'@about',
        BlogController::class.'@aboutTr',
        BlogController::class.'@index',
        BlogController::class.'@show',
        BlogController::class.'@indexTr',
        BlogController::class.'@showTr',
        PageController::class.'@show',
        PageController::class.'@showTr',
        SeoController::class.'@sitemap',
        SeoController::class.'@feed',
        SeoController::class.'@feedTr',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isCacheable($request, $response)) {
            return $response;
        }

        $cookieName = (string) config('session.cookie');

        // گاردِ ۲: بازدیدکننده‌ای که سشن دارد (ادمین/کاربرِ درگیر) پاسخِ خصوصی می‌گیرد، کش نمی‌شود.
        if ($request->cookies->has($cookieName)) {
            return $response;
        }

        // گاردِ ۳: کوکیِ سشن/XSRF را از این پاسخِ ناشناس حذف کن تا نسخه‌ی کش‌شده بی‌کاربر بماند.
        $path = (string) (config('session.path') ?: '/');
        $domain = config('session.domain');
        $response->headers->removeCookie($cookieName, $path, $domain);
        $response->headers->removeCookie('XSRF-TOKEN', $path, $domain);

        // به Cloudflare بگو مجاز به کشِ اشتراکی هست؛ مرورگرِ کاربر خودش هر بار تازه بگیرد (max-age=0)
        // تا HTMLِ کهنه در مرورگرِ کسی گیر نکند. s-maxage فقط برای کشِ اشتراکیِ لبه است.
        $ttl = max(0, (int) SiteSetting::get('edge_cache.ttl', 600));
        $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage='.$ttl);

        return $response;
    }

    private function isCacheable(Request $request, Response $response): bool
    {
        // فقط وقتی مدیر صریحاً روشنش کرده باشد (پیش‌فرض: خاموش → رفتارِ امروز)
        if (SiteSetting::get('edge_cache.enabled') !== '1') {
            return false;
        }

        // فقط GET/HEAD
        if (! $request->isMethodCacheable()) {
            return false;
        }

        // فقط پاسخِ موفقِ ۲۰۰ — خطاها/ریدایرکت‌ها نباید کش شوند
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // فقط اکشن‌های داخلِ allowlist
        $action = $request->route()?->getActionName();

        return $action !== null && in_array($action, self::CACHEABLE_ACTIONS, true);
    }
}
