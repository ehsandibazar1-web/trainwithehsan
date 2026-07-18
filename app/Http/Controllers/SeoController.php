<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Page;
use App\Services\Seo\VideoSchemaService;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    // نقشه‌ی سایت داینامیک — همیشه به‌روز، چون مستقیم از دیتابیس ساخته می‌شود. علاوه بر URLها،
    // برای هر مقاله/صفحه‌ای که ویدیوی درون‌متنی دارد، ورودی‌های Google Video Sitemap
    // (<video:video>) هم افزوده می‌شود — از همان VideoSchemaService که schemaِ HTML را می‌سازد،
    // پس یک منبعِ واحدِ تشخیص هر دو را تغذیه می‌کند.
    public function sitemap(VideoSchemaService $videoSchema)
    {
        $staticUrls = [
            url('/'),
            url('/about'),
            url('/blog'),
            url('/tr'),
            url('/tr/about'),
            url('/tr/blog'),
        ];

        $articles = Article::published()
            ->orderByDesc('published_at')
            ->get();

        // صفحات مستقل (Contact, FAQ, Privacy Policy, ...) — همه‌ی صفحات منتشرشده، نه فقط چند
        // اسلاگ ثابت، تا هر صفحه‌ی جدیدی که ادمین از پنل بسازد خودکار وارد سایت‌مپ شود
        // (همون رفتاری که مقالات از قبل دارند)
        $pages = Page::published()
            ->orderByDesc('updated_at')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

        foreach ($staticUrls as $u) {
            $xml .= "\n  <url><loc>{$u}</loc></url>";
        }

        foreach ($articles as $article) {
            $loc = url($article->path());
            $lastmod = optional($article->updated_at)->toAtomString();
            $xml .= "\n  <url><loc>{$loc}</loc>".($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '')
                .$this->videoEntries($videoSchema->forArticle($article)).'</url>';
        }

        foreach ($pages as $page) {
            $loc = url($page->path());
            $lastmod = optional($page->updated_at)->toAtomString();
            $xml .= "\n  <url><loc>{$loc}</loc>".($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '')
                .$this->videoEntries($videoSchema->forPage($page)).'</url>';
        }

        $xml .= "\n</urlset>";

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * VideoObjectهای یک صفحه را به بلاک‌های <video:video> نگاشت می‌کند (مشخصاتِ Google Video Sitemap).
     * هر بلاک الزاماً thumbnail_loc + title + description + (content_loc یا player_loc) دارد — همان
     * چیزی که VideoObject همیشه فراهم می‌کند — پس فقط ورودیِ معتبر تولید می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $videos
     */
    private function videoEntries(array $videos): string
    {
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = '';

        foreach ($videos as $v) {
            $title = $esc(Str::limit((string) ($v['name'] ?? ''), 100, ''));
            $thumb = $esc($v['thumbnailUrl'] ?? '');
            $player = $v['embedUrl'] ?? null;
            $content = $v['contentUrl'] ?? null;

            // بدونِ عنوان/تامبنیل/منبع → ورودیِ معتبرِ ویدیو نیست، رد می‌شود
            if ($title === '' || $thumb === '' || (! $player && ! $content)) {
                continue;
            }

            $desc = $esc(Str::limit((string) ($v['description'] ?? ($v['name'] ?? '')), 2000, ''));
            if ($desc === '') {
                $desc = $title;
            }

            $out .= "\n    <video:video>";
            $out .= "<video:thumbnail_loc>{$thumb}</video:thumbnail_loc>";
            $out .= "<video:title>{$title}</video:title>";
            $out .= "<video:description>{$desc}</video:description>";
            if ($content) {
                $out .= '<video:content_loc>'.$esc($content).'</video:content_loc>';
            }
            if ($player) {
                $out .= '<video:player_loc>'.$esc($player).'</video:player_loc>';
            }
            if (! empty($v['uploadDate'])) {
                $out .= '<video:publication_date>'.$esc($v['uploadDate']).'</video:publication_date>';
            }
            $out .= '</video:video>';
        }

        return $out;
    }

    // خوراک RSS — انگلیسی
    public function feed()
    {
        return $this->buildFeed('en', url('/blog'), 'Ehsan Dibazar — Blog', 'Self-defense and martial arts articles');
    }

    // خوراک RSS — ترکی
    public function feedTr()
    {
        return $this->buildFeed('tr', url('/tr/blog'), 'Ehsan Dibazar — Blog (Türkçe)', 'Kendini savunma ve dövüş sanatları makaleleri');
    }

    private function buildFeed(string $locale, string $link, string $title, string $description)
    {
        $articles = Article::published()
            ->locale($locale)
            ->orderByDesc('published_at')
            ->take(20)
            ->get();

        $esc = fn ($s) => htmlspecialchars($s ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0"><channel>';
        $xml .= "\n  <title>{$esc($title)}</title>";
        $xml .= "\n  <link>{$link}</link>";
        $xml .= "\n  <description>{$esc($description)}</description>";
        $xml .= "\n  <language>{$locale}</language>";

        foreach ($articles as $article) {
            $itemLink = url($article->path());
            $pubDate = optional($article->published_at)->toRssString();
            $xml .= "\n  <item>";
            $xml .= "<title>{$esc($article->title)}</title>";
            $xml .= "<link>{$itemLink}</link>";
            $xml .= "<guid>{$itemLink}</guid>";
            if ($pubDate) {
                $xml .= "<pubDate>{$pubDate}</pubDate>";
            }
            $xml .= "<description>{$esc($article->excerpt)}</description>";
            $xml .= '</item>';
        }

        $xml .= "\n</channel></rss>";

        return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
