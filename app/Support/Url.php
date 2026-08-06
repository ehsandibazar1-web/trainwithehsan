<?php

namespace App\Support;

class Url
{
    // جلوگیری از SSRF: هاست URL باید به یک IP عمومی resolve شود، نه به آدرس‌های خصوصی/loopback/
    // link-local/رزروشده (مثلاً 127.0.0.1 یا 169.254.169.254 که در بسیاری از سرویس‌های ابری
    // متادیتای داخلی سرور را برمی‌گرداند). این منطق عیناً از
    // ArticleImportService::isUrlSafeForServerFetch() استخراج شده — همان‌جا برای دانلودِ
    // featured_image استفاده می‌شد، این‌جا برای فچِ URLِ صفحه‌ی وب در Knowledge Base (RAG)
    // هم استفاده می‌شود؛ یک منبعِ واحد برای هر دو، نه دو کپیِ همان بررسی.
    public static function isSafeForServerFetch(string $url): bool
    {
        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        // اگر خودِ هاست یک IP لفظی است (مثل 127.0.0.1 یا 169.254.169.254)، باید عمومی باشد
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        // هاستِ نامی: هر IPای که resolve می‌شود باید عمومی باشد. اگر اصلاً resolve نشد
        // (مثلاً یک دامنه‌ی نمونه در محیط تست بدون DNS واقعی)، رد نمی‌شود — چون خودِ درخواستِ
        // HTTP بعدی به‌طور طبیعی با خطای اتصال شکست می‌خورد؛ این فقط برای هاست‌هایی که واقعاً
        // به یک آدرس خصوصی/رزروشده resolve می‌شوند سخت‌گیر است
        foreach (gethostbynamel($host) ?: [] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
