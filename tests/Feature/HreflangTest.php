<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * hreflang قبل از این تغییر دو مشکل داشت: نسخه‌ی EN اصلاً کامنت بود (فقط TR فعال بود، پس رابطه
 * یک‌طرفه بود)، و حتی نسخه‌ی TR هم چون هیچ صفحه‌ای @section('path_suffix', ...) را واقعاً پر
 * نمی‌کرد، همیشه به ریشه‌ی سایت (نه صفحه‌ی معادل واقعی) اشاره می‌کرد. حالا صفحات ثابت از مسیر
 * درخواست فعلی (با حذف پیشوند tr/) به‌صورت خودکار مسیر معادل را می‌سازند، و صفحات مقاله (که
 * اسلاگ EN/TR‌شان می‌تواند کاملاً متفاوت باشد) این رفتار را با @section('hreflang', ...) خودشان
 * به‌طور کامل override می‌کنند.
 */
class HreflangTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_has_hreflang_pointing_to_both_language_roots(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="en" href="https://trainwithehsan.com">', false);
        $response->assertSee('<link rel="alternate" hreflang="tr" href="https://trainwithehsan.com/tr">', false);
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="https://trainwithehsan.com">', false);
    }

    public function test_turkish_homepage_has_hreflang_pointing_to_both_language_roots(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="en" href="https://trainwithehsan.com">', false);
        $response->assertSee('<link rel="alternate" hreflang="tr" href="https://trainwithehsan.com/tr">', false);
    }

    public function test_english_about_page_hreflang_points_to_the_turkish_about_page_not_the_root(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="en" href="https://trainwithehsan.com/about">', false);
        $response->assertSee('<link rel="alternate" hreflang="tr" href="https://trainwithehsan.com/tr/about">', false);
    }

    public function test_turkish_about_page_hreflang_points_to_the_english_about_page(): void
    {
        $response = $this->get('/tr/about');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="en" href="https://trainwithehsan.com/about">', false);
        $response->assertSee('<link rel="alternate" hreflang="tr" href="https://trainwithehsan.com/tr/about">', false);
    }

    public function test_article_with_a_translation_points_to_its_actual_sibling_slug(): void
    {
        $en = Article::create([
            'locale' => 'en', 'title' => 'Combat Intelligence', 'slug' => 'combat-intelligence',
            'body' => '<p>x</p>', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now(),
        ]);
        Article::create([
            'locale' => 'tr', 'title' => 'Dövüş Zekâsı', 'slug' => 'dovus-zekasi',
            'translation_of' => $en->id,
            'body' => '<p>x</p>', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now(),
        ]);

        $enResponse = $this->get('/blog/combat-intelligence');
        $enResponse->assertOk();
        $enResponse->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/blog/combat-intelligence">', false);
        $enResponse->assertSee('<link rel="alternate" hreflang="tr" href="http://localhost/tr/blog/dovus-zekasi">', false);

        $trResponse = $this->get('/tr/blog/dovus-zekasi');
        $trResponse->assertOk();
        $trResponse->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/blog/combat-intelligence">', false);
        $trResponse->assertSee('<link rel="alternate" hreflang="tr" href="http://localhost/tr/blog/dovus-zekasi">', false);
    }

    public function test_article_without_a_translation_does_not_claim_a_guessed_sibling_url(): void
    {
        Article::create([
            'locale' => 'en', 'title' => 'Untranslated Post', 'slug' => 'untranslated-post',
            'body' => '<p>x</p>', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now(),
        ]);

        $response = $this->get('/blog/untranslated-post');

        $response->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/blog/untranslated-post">', false);
        // نباید هیچ hreflang="tr" ادعا کنه — چون هیچ ترجمه‌ای وجود نداره (نه حتی حدسی به همون اسلاگ)
        $response->assertDontSee('hreflang="tr"', false);
    }
}
