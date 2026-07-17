<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Seo\SeoAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoAuditTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SeoAuditService
    {
        return app(SeoAuditService::class);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en', 'title' => 'A Great Article', 'slug' => 'a-great-article',
            'excerpt' => 'A genuinely useful, standalone summary sentence about this article topic.',
            'body' => '<p>Body content here.</p>', 'author_name' => 'Ehsan', 'status' => 'published',
        ], $overrides));
    }

    // اسلاگ پیش‌فرض عمداً «privacy-policy» نیست — این اسلاگ الان توسط مهاجرت seed صفحات
    // Contact/FAQ/Legal (۲۰۲۶_۰۷_۱۷_۰۰۰۰۱۱) واقعاً پر شده و روی هر اجرای RefreshDatabase موجود
    // است؛ استفاده از همون اسلاگ اینجا با UNIQUE(slug, locale) تصادم می‌کرد
    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en', 'title' => 'Sample Standalone Page', 'slug' => 'sample-standalone-page',
            'body' => '<p>'.str_repeat('Long enough legal body text. ', 10).'</p>', 'status' => 'published',
        ], $overrides));
    }

    public function test_missing_titles_flags_blank_title(): void
    {
        $this->makeArticle(['title' => '  ', 'slug' => 'blank-title']);
        $this->makeArticle(['title' => 'Fine', 'slug' => 'fine-title']);

        $result = $this->service()->run();

        $this->assertCount(1, $result['missing_titles']);
    }

    public function test_missing_descriptions_flags_short_content_only(): void
    {
        $this->makeArticle(['excerpt' => null, 'body' => '<p>short</p>', 'slug' => 'thin']);
        $this->makeArticle(['excerpt' => 'A perfectly long and descriptive standalone summary sentence.', 'slug' => 'rich']);

        $result = $this->service()->run();

        $titles = array_column($result['missing_descriptions'], 'title');
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'A Great Article')) || count($result['missing_descriptions']) === 1);
        $this->assertCount(1, $result['missing_descriptions']);
    }

    public function test_missing_canonicals_is_always_empty_by_design(): void
    {
        $this->makeArticle();

        $result = $this->service()->run();

        $this->assertSame([], $result['missing_canonicals']);
    }

    public function test_missing_alt_flags_inline_body_images_and_in_use_dam_media(): void
    {
        $this->makeArticle([
            'slug' => 'with-inline-image',
            'body' => '<p>Text</p><img src="/storage/articles/inline/x.png">',
        ]);

        $media = Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/hero.jpg',
            'url' => 'http://x/hero.jpg', 'type' => 'image',
        ]);
        $this->makeArticle(['slug' => 'with-featured-image', 'image_path' => 'articles/hero.jpg']);

        $unusedMedia = Media::create([
            'original_name' => 'unused.jpg', 'disk' => 'public', 'disk_path' => 'media/unused.jpg',
            'url' => 'http://x/unused.jpg', 'type' => 'image',
        ]);

        $result = $this->service()->run();
        $combined = collect($result['missing_alt'])->map(fn ($f) => $f['title'].' '.$f['detail'])->implode(' | ');

        $this->assertStringContainsString('Inline image', $combined);
        $this->assertStringContainsString('hero.jpg', $combined);
        $this->assertStringNotContainsString('unused.jpg', $combined);
    }

    // از ۲۰۲۶-۰۷-۱۷ page.blade.php/tr/page.blade.php همیشه WebPage schema تولید می‌کنند (و در
    // صورت وجود faqs، FAQPage هم)، و از ۲۰۲۶-۰۷-۱۸ blog.blade.php/tr/blog.blade.php هم همیشه
    // CollectionPage تولید می‌کنند — پس دیگر هیچ‌چیزی در «Missing Schema» پرچم نمی‌خورد
    public function test_missing_schema_flags_nothing_pages_articles_and_blog_index_all_have_schema(): void
    {
        $this->makeArticle();
        $this->makePage();

        $result = $this->service()->run();

        $this->assertCount(0, collect($result['missing_schema'])->where('type', 'Page'));
        $this->assertCount(0, collect($result['missing_schema'])->where('type', 'Article'));
        $this->assertCount(0, collect($result['missing_schema'])->where('type', 'Blog index'));
        $this->assertEmpty($result['missing_schema']);
    }

    public function test_duplicate_titles_and_descriptions_are_grouped(): void
    {
        $this->makeArticle(['slug' => 'one', 'title' => 'Same Title', 'excerpt' => 'Same description text right here for both articles.']);
        $this->makeArticle(['slug' => 'two', 'title' => 'Same Title', 'excerpt' => 'Same description text right here for both articles.']);
        $this->makeArticle(['slug' => 'three', 'title' => 'Unique Title', 'excerpt' => 'A totally different unique description entirely.']);

        $result = $this->service()->run();

        $this->assertCount(2, $result['duplicate_titles']);
        $this->assertCount(2, $result['duplicate_descriptions']);
    }

    public function test_broken_internal_links_are_detected_and_valid_ones_are_not(): void
    {
        $this->makeArticle([
            'slug' => 'linker',
            'body' => '<p><a href="/blog/does-not-exist">dead</a> <a href="/blog/linker">self</a> <a href="https://example.com">external</a></p>',
        ]);

        $result = $this->service()->run();

        $this->assertCount(1, $result['broken_internal_links']);
        $this->assertStringContainsString('does-not-exist', $result['broken_internal_links'][0]['detail']);
    }

    public function test_orphan_pages_flags_published_unlinked_content_only(): void
    {
        $linked = $this->makePage(['slug' => 'linked-page', 'title' => 'Linked Page']);
        $orphan = $this->makePage(['slug' => 'orphan-page', 'title' => 'Orphan Page']);
        $this->makePage(['slug' => 'draft-page', 'title' => 'Draft Page', 'status' => 'draft']);

        $this->makeArticle([
            'slug' => 'links-to-linked-page',
            'body' => '<p><a href="/linked-page">see this</a></p>',
        ]);

        $result = $this->service()->run();
        $titles = array_column($result['orphan_pages'], 'title');

        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'Orphan Page')));
        $this->assertFalse(collect($titles)->contains(fn ($t) => str_contains($t, 'Linked Page')));
        $this->assertFalse(collect($titles)->contains(fn ($t) => str_contains($t, 'Draft Page')));
    }

    public function test_orphan_pages_considers_menu_and_footer_links(): void
    {
        $this->makePage(['slug' => 'in-menu', 'title' => 'In Menu Page']);
        SiteSetting::set('menu.en.items', json_encode([['label' => 'X', 'url' => '/in-menu']]));

        $result = $this->service()->run();
        $titles = array_column($result['orphan_pages'], 'title');

        $this->assertFalse(collect($titles)->contains(fn ($t) => str_contains($t, 'In Menu Page')));
    }

    public function test_check_external_links_flags_non_successful_responses(): void
    {
        $this->makeArticle([
            'slug' => 'ext-links',
            'body' => '<p><a href="https://good.example.com">ok</a> <a href="https://bad.example.com">bad</a></p>',
        ]);

        Http::fake([
            'good.example.com*' => Http::response('ok', 200),
            'bad.example.com*' => Http::response('nope', 404),
            // fallback موفق برای هر دامنه‌ی خارجی دیگر — این چک روی کل سایت اجرا می‌شود، پس با
            // لینک‌های خارجی واقعیِ صفحات seed-شده (مثلا policies.google.com در Cookie Policy)
            // هم برخورد می‌کند؛ اینجا فقط bad.example.com عمداً «خراب» فرض شده
            '*' => Http::response('ok', 200),
        ]);

        $findings = $this->service()->checkExternalLinks();

        $badLinkFindings = collect($findings)->filter(fn ($f) => str_contains($f['detail'], 'bad.example.com'))->values();

        $this->assertCount(1, $badLinkFindings);
        $this->assertStringContainsString('bad.example.com', $badLinkFindings[0]['detail']);
    }
}
