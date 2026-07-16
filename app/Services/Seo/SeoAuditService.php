<?php

namespace App\Services\Seo;

use App\Filament\Pages\FooterSettings;
use App\Filament\Pages\MediaLibrary;
use App\Filament\Pages\MenuSettings;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * موتور بررسی سئوی سایت — روی داده‌های همین اپ (مقاله/صفحه/منو/فوتر/کتابخانه‌ی رسانه) کار می‌کند،
 * هیچ پیاده‌سازی سئوی موجود (SeoController، تگ‌های master.blade.php، DAM) را جایگزین نمی‌کند —
 * فقط آن‌ها را برای گزارش‌دهی می‌خواند. برای جزئیات هر بخش به بخش «SEO Center» در CLAUDE.md مراجعه کنید.
 */
class SeoAuditService
{
    // آستانه‌ی توضیحات کوتاه/بی‌کیفیت — همون چیزی که در متا توضیحات واقعی سایت رندر می‌شود
    private const MIN_DESCRIPTION_LENGTH = 50;

    private const RESERVED_PAGE_SLUGS = ['admin', 'blog', 'about', 'tr', 'feed', 'preview', 'storage', 'livewire'];

    public function __construct(private readonly HtmlContentScanner $scanner) {}

    /**
     * تمام بررسی‌های سریع (بدون تماس شبکه‌ای) — برای اجرای خودکار در بارگذاری صفحه.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function run(): array
    {
        $items = $this->collectContentItems();

        return [
            'missing_titles' => $this->missingTitles($items),
            'missing_descriptions' => $this->missingDescriptions($items),
            'missing_canonicals' => $this->missingCanonicals(),
            'missing_alt' => $this->missingAlt($items),
            'missing_schema' => $this->missingSchema($items),
            'duplicate_titles' => $this->duplicateTitles($items),
            'duplicate_descriptions' => $this->duplicateDescriptions($items),
            'broken_internal_links' => $this->brokenInternalLinks($items),
            'orphan_pages' => $this->orphanPages($items),
        ];
    }

    /**
     * لینک‌های خارجی یکتای یافت‌شده در محتوا — بدون تماس شبکه‌ای (برای دکمه‌ی «اسکن لینک‌های خارجی»).
     *
     * @return Collection<int, array{url: string, sources: array<int, array<string, mixed>>}>
     */
    public function externalLinkTargets(): Collection
    {
        $items = $this->collectContentItems();
        $grouped = [];

        foreach ($this->allLinkSources($items) as $source) {
            foreach ($this->scanner->links($source['html']) as $link) {
                if (! $this->isExternal($link['href'])) {
                    continue;
                }

                $url = $link['href'];
                $grouped[$url]['url'] ??= $url;
                $grouped[$url]['sources'][] = $source['meta'];
            }
        }

        return collect(array_values($grouped));
    }

    /**
     * بررسی واقعی لینک‌های خارجی از طریق HTTP — عمداً جدا از run() چون کند و شبکه‌ای است؛
     * فقط با کلیک دکمه‌ی «اسکن لینک‌های خارجی» در پنل اجرا می‌شود.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkExternalLinks(): array
    {
        $targets = $this->externalLinkTargets();
        if ($targets->isEmpty()) {
            return [];
        }

        $urls = $targets->pluck('url')->all();

        $responses = Http::pool(fn ($pool) => collect($urls)->map(
            fn (string $url) => $pool->as($url)->timeout(6)->connectTimeout(4)->head($url)
        )->all());

        $findings = [];
        foreach ($targets as $target) {
            $response = $responses[$target['url']] ?? null;
            $broken = $response === null
                || $response instanceof \Throwable
                || ! $response->successful();

            if (! $broken) {
                continue;
            }

            $status = ($response && ! ($response instanceof \Throwable)) ? $response->status() : null;

            foreach ($target['sources'] as $source) {
                $findings[] = [
                    'category' => 'broken_external_links',
                    'type' => $source['type'],
                    'locale' => $source['locale'],
                    'title' => $source['label'],
                    'detail' => $status
                        ? "External link returns HTTP {$status}: {$target['url']}"
                        : "External link could not be reached: {$target['url']}",
                    'edit_url' => $source['edit_url'],
                ];
            }
        }

        return $findings;
    }

    /**
     * نرمال‌سازی مقاله‌ها و صفحه‌های مستقل به یک شکل یکسان برای بررسی‌های عنوان/توضیحات/لینک/orphan.
     * Home و About عمداً اینجا نیستند — محتوای آن‌ها متن ثابتِ دستی در Blade/SiteSetting است، نه رکورد
     * قابل‌ویرایشِ تکراری، پس چک «تکراری/گمشده» برایشان معنی‌دار نیست (نویز کاذب تولید می‌کند).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function collectContentItems(): Collection
    {
        $articles = Article::all()->map(function (Article $article) {
            return [
                'model' => 'Article',
                'id' => $article->id,
                'locale' => $article->locale,
                'status' => $article->status,
                'title' => $article->title,
                'raw_description' => filled($article->excerpt) ? $article->excerpt : strip_tags($article->body ?? ''),
                'body' => $article->body,
                'path' => $article->path(),
                'edit_url' => ArticleResource::getUrl('edit', ['record' => $article->id]),
            ];
        });

        $pages = Page::all()->map(function (Page $page) {
            return [
                'model' => 'Page',
                'id' => $page->id,
                'locale' => $page->locale,
                'status' => $page->status,
                'title' => $page->title,
                'raw_description' => strip_tags($page->body ?? ''),
                'body' => $page->body,
                'path' => $page->path(),
                'edit_url' => PageResource::getUrl('edit', ['record' => $page->id]),
            ];
        });

        return $articles->concat($pages)->values();
    }

    private function missingTitles(Collection $items): array
    {
        return $items
            ->filter(fn ($item) => trim((string) $item['title']) === '')
            ->map(fn ($item) => $this->finding($item, 'missing_titles', 'Title is empty.'))
            ->values()->all();
    }

    private function missingDescriptions(Collection $items): array
    {
        return $items
            ->filter(fn ($item) => mb_strlen(trim((string) $item['raw_description'])) < self::MIN_DESCRIPTION_LENGTH)
            ->map(fn ($item) => $this->finding(
                $item,
                'missing_descriptions',
                $item['model'] === 'Article'
                    ? 'No excerpt set, and the article body is too short to produce a good auto-generated description.'
                    : 'This page has no dedicated meta-description field — its description is auto-generated from the body, which is too short here.'
            ))
            ->values()->all();
    }

    /**
     * canonical برای هر صفحه‌ی این اپ خودکار تولید می‌شود (master.blade.php:
     * `@yield('canonical', url()->current())`) — پس این بررسی طبق طراحیِ فعلی همیشه خالی است؛
     * اینجا نگه داشته شده تا اگر روزی این fallback از layout حذف شد، بلافاصله دیده شود.
     */
    private function missingCanonicals(): array
    {
        return [];
    }

    private function missingAlt(Collection $items): array
    {
        $findings = [];

        // تصاویر داخل متن مقاله/صفحه — این‌ها رکورد Media ندارند، پس DAM آن‌ها را نمی‌بیند
        foreach ($items as $item) {
            foreach ($this->scanner->images($item['body']) as $image) {
                if ($image['alt'] !== '') {
                    continue;
                }

                $findings[] = $this->finding(
                    $item,
                    'missing_alt',
                    "Inline image in the body has no ALT text: {$image['src']}"
                );
            }
        }

        // تصاویر کتابخانه‌ی رسانه (مثلا تصویر شاخص مقاله/صفحه) که واقعا در سایت استفاده می‌شوند
        Media::where('type', 'image')
            ->where(function ($q) {
                $q->whereNull('alt_text')->orWhere('alt_text', '');
            })
            ->get()
            ->each(function (Media $media) use (&$findings) {
                $usages = $media->usages();
                if (empty($usages)) {
                    return;
                }

                foreach ($usages as $usage) {
                    $findings[] = [
                        'category' => 'missing_alt',
                        'type' => 'Media',
                        'locale' => null,
                        'title' => $usage['label'].' — '.$media->original_name,
                        'detail' => "Media Library image used as \"{$usage['field']}\" has no ALT text.",
                        'edit_url' => MediaLibrary::getUrl(['media' => $media->id]),
                    ];
                }
            });

        return $findings;
    }

    private function missingSchema(Collection $items): array
    {
        // مقاله‌ها همیشه schema Article دارند (blog-post.blade.php) — اینجا فقط صفحات مستقل چک می‌شوند
        // که هیچ‌گاه json-ld تولید نمی‌کنند (page.blade.php این @section را ندارد)
        $findings = $items
            ->filter(fn ($item) => $item['model'] === 'Page')
            ->map(fn ($item) => $this->finding(
                $item,
                'missing_schema',
                'Standalone pages have no JSON-LD template (page.blade.php does not emit one) — this is a template gap, not something fixable by editing this page.'
            ))
            ->values()->all();

        // ایندکس بلاگ هم schema ندارد (blog.blade.php)
        foreach (['en' => '/blog', 'tr' => '/tr/blog'] as $locale => $path) {
            $findings[] = [
                'category' => 'missing_schema',
                'type' => 'Blog index',
                'locale' => $locale,
                'title' => 'Blog index ('.strtoupper($locale).')',
                'detail' => "The blog listing page ({$path}) has no JSON-LD structured data.",
                'edit_url' => null,
            ];
        }

        return $findings;
    }

    private function duplicateTitles(Collection $items): array
    {
        return $this->duplicatesBy($items, fn ($item) => trim((string) $item['title']), 'duplicate_titles', 'title');
    }

    private function duplicateDescriptions(Collection $items): array
    {
        return $this->duplicatesBy(
            $items,
            fn ($item) => trim((string) $item['raw_description']),
            'duplicate_descriptions',
            'meta description'
        );
    }

    private function duplicatesBy(Collection $items, \Closure $valueOf, string $category, string $label): array
    {
        $findings = [];

        $items
            ->filter(fn ($item) => $valueOf($item) !== '')
            ->groupBy(fn ($item) => mb_strtolower($valueOf($item)))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use (&$findings, $category, $label, $valueOf) {
                $group->each(function ($item) use (&$findings, $category, $label, $group, $valueOf) {
                    $others = $group->reject(fn ($other) => $other['model'] === $item['model'] && $other['id'] === $item['id']);
                    $findings[] = $this->finding(
                        $item,
                        $category,
                        "Same {$label} as: ".$others->map(fn ($o) => $o['title'].' ('.strtoupper($o['locale']).')')->implode(', ')."\n\"".Str::limit($valueOf($item), 80).'"'
                    );
                });
            });

        return $findings;
    }

    private function brokenInternalLinks(Collection $items): array
    {
        $findings = [];

        foreach ($this->allLinkSources($items) as $source) {
            foreach ($this->scanner->links($source['html']) as $link) {
                if ($this->isExternal($link['href']) || $this->isSkippable($link['href'])) {
                    continue;
                }

                if ($this->internalPathExists($link['href'])) {
                    continue;
                }

                $findings[] = [
                    'category' => 'broken_internal_links',
                    'type' => $source['meta']['type'],
                    'locale' => $source['meta']['locale'],
                    'title' => $source['meta']['label'],
                    'detail' => "Links to \"{$link['href']}\", which does not match any known route or existing slug.",
                    'edit_url' => $source['meta']['edit_url'],
                ];
            }
        }

        return $findings;
    }

    private function orphanPages(Collection $items): array
    {
        $linkedPaths = collect();

        foreach ($this->allLinkSources($items) as $source) {
            foreach ($this->scanner->links($source['html']) as $link) {
                if ($this->isExternal($link['href']) || $this->isSkippable($link['href'])) {
                    continue;
                }

                $linkedPaths->push($this->normalizedPath($link['href']));
            }
        }

        $linkedPaths = $linkedPaths->unique();

        return $items
            ->filter(fn ($item) => $item['status'] === 'published')
            ->filter(fn ($item) => ! $linkedPaths->contains($this->normalizedPath($item['path'])))
            ->map(function ($item) {
                $extra = $item['model'] === 'Page'
                    ? ' It is also not included in sitemap.xml (standalone pages are not part of the sitemap today — see SeoController).'
                    : '';

                return $this->finding(
                    $item,
                    'orphan_pages',
                    'No internal link to this page was found in any article/page body, the header menu, or the footer.'.$extra
                );
            })
            ->values()->all();
    }

    /**
     * منابع لینک‌های داخلیِ سایت — بدنه‌ی مقاله/صفحه + منوی هدر + ستون‌های فوتر (هر دو زبان).
     * این همان جاهایی است که یک بازدیدکننده یا Googlebot می‌تواند از آن‌ها به صفحات دیگر برسد.
     *
     * @return array<int, array{html: ?string, meta: array<string, mixed>}>
     */
    private function allLinkSources(Collection $items): array
    {
        $sources = $items->map(fn ($item) => [
            'html' => $item['body'],
            'meta' => [
                'type' => $item['model'],
                'locale' => $item['locale'],
                'label' => $item['title'].' ('.strtoupper($item['locale']).')',
                'edit_url' => $item['edit_url'],
            ],
        ])->all();

        foreach (['en', 'tr'] as $locale) {
            $menuHtml = collect(SiteSetting::getJson("menu.{$locale}.items"))
                ->map(fn ($i) => '<a href="'.($i['url'] ?? '').'"></a>')
                ->implode('');

            if ($menuHtml !== '') {
                $sources[] = [
                    'html' => $menuHtml,
                    'meta' => [
                        'type' => 'Menu',
                        'locale' => $locale,
                        'label' => 'Header menu ('.strtoupper($locale).')',
                        'edit_url' => MenuSettings::getUrl(),
                    ],
                ];
            }

            $footerLinks = collect(SiteSetting::getJson("footer.{$locale}.columns"))
                ->flatMap(fn ($column) => $column['links'] ?? []);
            $footerHtml = $footerLinks->map(fn ($l) => '<a href="'.($l['url'] ?? '').'"></a>')->implode('');

            if ($footerHtml !== '') {
                $sources[] = [
                    'html' => $footerHtml,
                    'meta' => [
                        'type' => 'Footer',
                        'locale' => $locale,
                        'label' => 'Footer links ('.strtoupper($locale).')',
                        'edit_url' => FooterSettings::getUrl(),
                    ],
                ];
            }
        }

        return $sources;
    }

    private function isExternal(string $href): bool
    {
        $host = parse_url($href, PHP_URL_HOST);
        if ($host === null) {
            return false; // مسیر نسبی — داخلی
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $appHost === null || strcasecmp($host, $appHost) !== 0;
    }

    private function isSkippable(string $href): bool
    {
        return $href === '' || $href === '#' || str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:');
    }

    private function normalizedPath(string $href): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?? '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * آیا این مسیر داخلی، مسیر واقعی سایت است؟ منعکس‌کننده‌ی مسیرهای ثبت‌شده در routes/web.php —
     * اگر آن فایل تغییر کرد، این متد هم باید هماهنگ شود.
     */
    private function internalPathExists(string $href): bool
    {
        $path = $this->normalizedPath($href);

        $static = ['/', '/tr', '/about', '/tr/about', '/blog', '/tr/blog', '/feed', '/tr/feed', '/sitemap.xml'];
        if (in_array($path, $static, true)) {
            return true;
        }

        if (preg_match('#^/blog/([A-Za-z0-9-]+)$#', $path, $m)) {
            return Article::where('locale', 'en')->where('slug', $m[1])->exists();
        }

        if (preg_match('#^/tr/blog/([A-Za-z0-9-]+)$#', $path, $m)) {
            return Article::where('locale', 'tr')->where('slug', $m[1])->exists();
        }

        if (preg_match('#^/tr/([A-Za-z0-9-]+)$#', $path, $m) && ! in_array($m[1], self::RESERVED_PAGE_SLUGS, true)) {
            return Page::where('locale', 'tr')->where('slug', $m[1])->exists();
        }

        if (preg_match('#^/([A-Za-z0-9-]+)$#', $path, $m) && ! in_array($m[1], self::RESERVED_PAGE_SLUGS, true)) {
            return Page::where('locale', 'en')->where('slug', $m[1])->exists();
        }

        return false;
    }

    private function finding(array $item, string $category, string $detail): array
    {
        return [
            'category' => $category,
            'type' => $item['model'],
            'locale' => $item['locale'],
            'title' => $item['title'].' ('.strtoupper($item['locale']).')',
            'detail' => $detail,
            'edit_url' => $item['edit_url'],
        ];
    }
}
