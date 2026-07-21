<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اولویت ۴ی Testing Strategy: SeoController — سایت‌مپ فقط محتوای منتشرشده را دارد (مقاله و
 * صفحه‌ی مستقل، هر دو زبان) و خوراک‌های RSS خروجیِ XMLِ سالم و زبان-جدا می‌دهند.
 */
class SeoFeedsTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Sitemap Article',
            'slug' => 'sitemap-article-'.uniqid(),
            'excerpt' => 'A quotable excerpt for the feed.',
            'body' => '<p>Body content.</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_sitemap_is_valid_xml_with_the_static_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        // XMLِ خراب یعنی گوگل کلِ نقشه را دور می‌ریزد — سالم‌بودنِ ساختار مهم‌تر از محتواست
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);

        foreach (['/', '/about', '/blog', '/tr', '/tr/about', '/tr/blog'] as $path) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', $response->getContent());
        }
    }

    public function test_sitemap_includes_published_articles_in_both_locales_and_excludes_unpublished(): void
    {
        $en = $this->makeArticle();
        $tr = $this->makeArticle(['locale' => 'tr']);
        $draft = $this->makeArticle(['status' => 'draft', 'published_at' => null]);
        $future = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->addDay()]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url($en->path()), $content);
        $this->assertStringContainsString(url($tr->path()), $content);
        $this->assertStringNotContainsString($draft->slug, $content);
        $this->assertStringNotContainsString($future->slug, $content);
    }

    public function test_sitemap_includes_published_pages_and_excludes_draft_pages(): void
    {
        // صفحاتِ seedشده (contact و ...) منتشرشده‌اند و باید باشند؛ صفحه‌ی پیش‌نویسِ تازه نه
        $draftPage = Page::create([
            'locale' => 'en', 'title' => 'Draft Sitemap Page', 'slug' => 'draft-sitemap-page-'.uniqid(),
            'body' => '<p>x</p>', 'status' => 'draft',
        ]);

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/contact'), $content);
        $this->assertStringNotContainsString($draftPage->slug, $content);
    }

    public function test_english_feed_is_valid_locale_filtered_rss_with_the_excerpt(): void
    {
        $en = $this->makeArticle(['title' => 'English Feed Article']);
        $this->makeArticle(['title' => 'Türkçe Feed Makalesi', 'locale' => 'tr']);
        $this->makeArticle(['title' => 'Draft Feed Article', 'status' => 'draft', 'published_at' => null]);

        $response = $this->get('/feed');

        $response->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);

        $content = $response->getContent();
        $this->assertStringContainsString('English Feed Article', $content);
        // متنِ excerpt عیناً در <description> می‌رود — همان چیزی که موتورهای AI/RSSخوان‌ها برمی‌دارند
        $this->assertStringContainsString('A quotable excerpt for the feed.', $content);
        $this->assertStringContainsString(url($en->path()), $content);
        $this->assertStringNotContainsString('Türkçe Feed Makalesi', $content);
        $this->assertStringNotContainsString('Draft Feed Article', $content);
    }

    public function test_turkish_feed_contains_only_turkish_articles(): void
    {
        $this->makeArticle(['title' => 'English Feed Article']);
        $tr = $this->makeArticle(['title' => 'Türkçe Feed Makalesi', 'locale' => 'tr']);

        $content = $this->get('/tr/feed')->assertOk()->getContent();

        $this->assertStringContainsString('Türkçe Feed Makalesi', $content);
        $this->assertStringContainsString(url($tr->path()), $content);
        $this->assertStringNotContainsString('English Feed Article', $content);
    }
}
