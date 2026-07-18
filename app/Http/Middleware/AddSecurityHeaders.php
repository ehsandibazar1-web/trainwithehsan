<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هدرهای امنیتیِ سطح مرورگر — دفاعِ مکمل، نه جایگزینِ پاکسازیِ HTML (ArticleImportService).
 *
 * Content-Security-Policy عمداً به‌صورت Report-Only است، نه اجرایی: این سایت اسکریپت/استایلِ
 * inline زیادی دارد (کاروسل‌ها، منوی موبایل، رضایتِ کوکی، اسلایدر) و چند اسکریپتِ شخص‌ثالث
 * (Ahrefs، GTM/gtag، Microsoft Clarity) که زیرمنبع‌های دقیقشان قابل تضمینِ کامل نیست. حالتِ
 * Report-Only هیچ درخواستی را مسدود نمی‌کند — فقط تخلف‌ها را در کنسولِ مرورگر گزارش می‌دهد، تا
 * بشود قبل از سخت‌گیرانه‌کردنِ واقعی، مطمئن شد چیزی (خصوصاً آنالیتیکس) نمی‌شکند.
 */
class AddSecurityHeaders
{
    private const CSP_REPORT_ONLY = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' https://analytics.ahrefs.com https://www.googletagmanager.com https://www.clarity.ms https://*.clarity.ms https://www.google-analytics.com https://*.google-analytics.com; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src 'self' https://fonts.gstatic.com data:; "
        ."img-src 'self' data: https:; "
        ."connect-src 'self' https://analytics.ahrefs.com https://www.googletagmanager.com https://www.clarity.ms https://*.clarity.ms https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com; "
        .'frame-src https://www.youtube.com https://player.vimeo.com; '
        ."frame-ancestors 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy-Report-Only', self::CSP_REPORT_ONLY);

        return $response;
    }
}
