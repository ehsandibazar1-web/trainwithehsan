<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اولویت ۳ی Testing Strategy: مسیرهای عمومیِ BlogController در هر دو زبان — صفحه‌ی اصلی/لیست/
 * تک‌مقاله ۲۰۰ می‌دهند، مقاله‌ی درست را نشان می‌دهند، زبان‌ها قاطی نمی‌شوند، اسلاگِ ناموجود
 * (یا پیش‌نویس) ۴۰۴ می‌گیرد، شمارنده‌ی بازدید و لینکِ نسخه‌ی زبانِ دیگر کار می‌کنند.
 */
class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Blog Controller Article',
            'slug' => 'blog-controller-'.uniqid(),
            'excerpt' => 'A short standalone excerpt.',
            'body' => '<p>Body content.</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_homepage_shows_latest_english_articles_only(): void
    {
        $en = $this->makeArticle(['title' => 'English Home Article']);
        $this->makeArticle(['title' => 'Türkçe Ana Sayfa Makalesi', 'locale' => 'tr']);

        $this->get('/')->assertOk()
            ->assertSee('English Home Article')
            ->assertDontSee('Türkçe Ana Sayfa Makalesi');
    }

    public function test_turkish_homepage_shows_turkish_articles_only(): void
    {
        $this->makeArticle(['title' => 'English Home Article']);
        $this->makeArticle(['title' => 'Türkçe Ana Sayfa Makalesi', 'locale' => 'tr']);

        $this->get('/tr')->assertOk()
            ->assertSee('Türkçe Ana Sayfa Makalesi')
            ->assertDontSee('English Home Article');
    }

    public function test_blog_index_lists_published_and_hides_unpublished(): void
    {
        $published = $this->makeArticle(['title' => 'Visible Published Article']);
        $this->makeArticle(['title' => 'Hidden Draft Article', 'status' => 'draft', 'published_at' => null]);
        $this->makeArticle(['title' => 'Hidden Future Article', 'status' => 'scheduled', 'published_at' => now()->addDay()]);

        $this->get('/blog')->assertOk()
            ->assertSee('Visible Published Article')
            ->assertDontSee('Hidden Draft Article')
            ->assertDontSee('Hidden Future Article');
    }

    public function test_turkish_blog_index_is_locale_isolated(): void
    {
        $this->makeArticle(['title' => 'English Only Article']);
        $this->makeArticle(['title' => 'Sadece Türkçe Makale', 'locale' => 'tr']);

        $this->get('/tr/blog')->assertOk()
            ->assertSee('Sadece Türkçe Makale')
            ->assertDontSee('English Only Article');
    }

    public function test_show_renders_the_article_and_increments_views(): void
    {
        $article = $this->makeArticle(['views' => 5]);

        $this->get('/blog/'.$article->slug)->assertOk()->assertSee($article->title);

        $this->assertSame(6, $article->fresh()->views);
    }

    public function test_show_returns_404_for_a_missing_slug(): void
    {
        $this->get('/blog/definitely-not-a-real-slug')->assertNotFound();
    }

    public function test_show_returns_404_for_a_draft_article(): void
    {
        $draft = $this->makeArticle(['status' => 'draft', 'published_at' => null]);

        $this->get('/blog/'.$draft->slug)->assertNotFound();
    }

    public function test_show_does_not_serve_an_article_on_the_other_locales_route(): void
    {
        $en = $this->makeArticle();

        // مقاله‌ی انگلیسی روی مسیرِ ترکی نباید resolve شود — جداییِ زبان‌ها (مدلِ دو-ردیفه)
        $this->get('/tr/blog/'.$en->slug)->assertNotFound();
    }

    public function test_show_serves_a_due_scheduled_article_even_before_the_cron_runs(): void
    {
        // تورِ ایمنیِ scopePublished باید تا خودِ صفحه‌ی عمومی هم برسد، نه فقط در کوئری
        $due = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->subMinute()]);

        $this->get('/blog/'.$due->slug)->assertOk()->assertSee($due->title);
    }

    public function test_show_links_the_translated_counterpart(): void
    {
        $en = $this->makeArticle(['title' => 'Linked English Article']);
        $tr = $this->makeArticle([
            'title' => 'Bağlantılı Türkçe Makale', 'locale' => 'tr', 'translation_of' => $en->id,
        ]);

        // هر دو جهتِ لینک (translation_of یک‌طرفه ذخیره می‌شود، renderShow دوطرفه چک می‌کند)
        $this->get('/blog/'.$en->slug)->assertOk()->assertSee($tr->path());
        $this->get('/tr/blog/'.$tr->slug)->assertOk()->assertSee($en->path());
    }

    public function test_show_lists_related_articles_from_the_same_category(): void
    {
        $article = $this->makeArticle(['category' => 'Self Defense']);
        $related = $this->makeArticle(['title' => 'Related Category Article', 'category' => 'Self Defense']);
        $unrelated = $this->makeArticle(['title' => 'Unrelated Category Article', 'category' => 'Nutrition']);

        // روی داده‌ی view چک می‌شود، نه کلِ HTML — مقاله‌ی غیرهم‌دسته ممکن است مشروعاً در
        // سایدبارِ «آخرین مقالات» (latest، بدونِ فیلترِ دسته) دیده شود
        $this->get('/blog/'.$article->slug)->assertOk()
            ->assertSee('Related Category Article')
            ->assertViewHas('related', fn ($items) => $items->pluck('id')->contains($related->id)
                && ! $items->pluck('id')->contains($unrelated->id));
    }
}
