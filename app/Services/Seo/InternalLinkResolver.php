<?php

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Page;

/**
 * تشخیص و بررسیِ لینک‌های داخلی سایت — استخراج‌شده از SeoAuditService تا هم آن سرویس و هم
 * LinkGraphService (کتابخانه‌ی داخلی لینک‌سازی) از یک منطق واحد استفاده کنند، نه دو نسخه‌ی جدا.
 */
class InternalLinkResolver
{
    private const RESERVED_PAGE_SLUGS = ['admin', 'blog', 'about', 'tr', 'feed', 'preview', 'storage', 'livewire'];

    private const STATIC_PATHS = ['/', '/tr', '/about', '/tr/about', '/blog', '/tr/blog', '/feed', '/tr/feed', '/sitemap.xml'];

    public function isExternal(string $href): bool
    {
        $host = parse_url($href, PHP_URL_HOST);
        if ($host === null) {
            return false; // مسیر نسبی — داخلی
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $appHost === null || strcasecmp($host, $appHost) !== 0;
    }

    public function isSkippable(string $href): bool
    {
        return $href === '' || $href === '#' || str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:');
    }

    public function normalizedPath(string $href): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?? '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * فقط شکل مسیر را تشخیص می‌دهد — بدون کوئری دیتابیس. برای ساخت گراف که باید روی صدها
     * لینک تکرار شود این نسخه‌ی بدون‌کوئری استفاده می‌شود (نگاه کنید به LinkGraphService).
     *
     * @return array{type: 'Article'|'Page', locale: string, slug: string}|null
     */
    public function parseInternalPath(string $href): ?array
    {
        if ($this->isExternal($href)) {
            return null;
        }

        $path = $this->normalizedPath($href);

        if (preg_match('#^/blog/([A-Za-z0-9-]+)$#', $path, $m)) {
            return ['type' => 'Article', 'locale' => 'en', 'slug' => $m[1]];
        }

        if (preg_match('#^/tr/blog/([A-Za-z0-9-]+)$#', $path, $m)) {
            return ['type' => 'Article', 'locale' => 'tr', 'slug' => $m[1]];
        }

        if (preg_match('#^/tr/([A-Za-z0-9-]+)$#', $path, $m) && ! in_array($m[1], self::RESERVED_PAGE_SLUGS, true)) {
            return ['type' => 'Page', 'locale' => 'tr', 'slug' => $m[1]];
        }

        if (preg_match('#^/([A-Za-z0-9-]+)$#', $path, $m) && ! in_array($m[1], self::RESERVED_PAGE_SLUGS, true)) {
            return ['type' => 'Page', 'locale' => 'en', 'slug' => $m[1]];
        }

        return null;
    }

    public function isKnownStaticPath(string $href): bool
    {
        return in_array($this->normalizedPath($href), self::STATIC_PATHS, true);
    }

    /**
     * آیا این مسیر داخلی، مسیر واقعی سایت است؟ منعکس‌کننده‌ی مسیرهای ثبت‌شده در routes/web.php —
     * اگر آن فایل تغییر کرد، این متد (و parseInternalPath) هم باید هماهنگ شوند.
     * برخلاف parseInternalPath، این متد واقعا در دیتابیس چک می‌کند که اسلاگ وجود دارد یا نه —
     * برای چک‌کردن یک لینک؛ برای ساخت گراف روی مجموعه‌ی بزرگی از لینک‌ها به‌جایش parseInternalPath
     * + یک نقشه‌ی از‌پیش‌بارگذاری‌شده در حافظه استفاده کنید تا کوئری تکراری نزنید.
     */
    public function internalPathExists(string $href): bool
    {
        if ($this->isKnownStaticPath($href)) {
            return true;
        }

        $parsed = $this->parseInternalPath($href);
        if (! $parsed) {
            return false;
        }

        return $parsed['type'] === 'Article'
            ? Article::where('locale', $parsed['locale'])->where('slug', $parsed['slug'])->exists()
            : Page::where('locale', $parsed['locale'])->where('slug', $parsed['slug'])->exists();
    }
}
