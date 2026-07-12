<?php

namespace App\Http\Controllers;

use App\Models\Article;

class SeoController extends Controller
{
    // نقشه‌ی سایت داینامیک — همیشه به‌روز، چون مستقیم از دیتابیس ساخته می‌شود
    public function sitemap()
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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($staticUrls as $u) {
            $xml .= "\n  <url><loc>{$u}</loc></url>";
        }

        foreach ($articles as $article) {
            $loc = url($article->path());
            $lastmod = optional($article->updated_at)->toAtomString();
            $xml .= "\n  <url><loc>{$loc}</loc>" . ($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '') . '</url>';
        }

        $xml .= "\n</urlset>";

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
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
