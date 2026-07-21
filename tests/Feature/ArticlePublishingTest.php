<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مهم‌ترین منطقِ تجاریِ اپ (اولویت‌های ۱ و ۲ی Testing Strategy در CLAUDE.md):
 * Article::scopePublished() — دیده‌شدنِ عمومیِ مقاله بر اساس وضعیت/زمان، شاملِ تورِ ایمنیِ
 * «زمان‌بندی‌شده‌ای که زمانش رسیده حتی بدونِ کرون نمایش داده می‌شود» — و فرمانِ
 * articles:publish-due که مقالاتِ سررسیدشده را واقعاً به published برمی‌گرداند.
 */
class ArticlePublishingTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Publishing Workflow Article',
            'slug' => 'publishing-workflow-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_published_articles_are_visible(): void
    {
        $article = $this->makeArticle();

        $this->assertTrue(Article::published()->whereKey($article->id)->exists());
    }

    public function test_draft_articles_are_hidden(): void
    {
        $article = $this->makeArticle(['status' => 'draft', 'published_at' => null]);

        $this->assertFalse(Article::published()->whereKey($article->id)->exists());
    }

    public function test_scheduled_articles_with_a_future_date_are_hidden(): void
    {
        $article = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->addDay()]);

        $this->assertFalse(Article::published()->whereKey($article->id)->exists());
    }

    public function test_scheduled_articles_whose_time_has_passed_are_visible_even_without_the_cron(): void
    {
        // تورِ ایمنیِ scopePublished: وضعیت هنوز scheduled است (کرون نزده) ولی زمانش گذشته —
        // باید دیده شود؛ حذفِ این رفتار یعنی توقفِ کرون محتوای سررسیده را برای همیشه پنهان می‌کند
        $article = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->subMinute()]);

        $this->assertTrue(Article::published()->whereKey($article->id)->exists());
    }

    public function test_publish_due_flips_due_scheduled_articles_to_published(): void
    {
        $due = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->subMinute()]);

        $this->artisan('articles:publish-due')->assertExitCode(0);

        $this->assertSame('published', $due->fresh()->status);
    }

    public function test_publish_due_leaves_future_scheduled_and_draft_articles_untouched(): void
    {
        $future = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->addDay()]);
        $draft = $this->makeArticle(['status' => 'draft', 'published_at' => null]);
        $noDate = $this->makeArticle(['status' => 'scheduled', 'published_at' => null]);

        $this->artisan('articles:publish-due')->assertExitCode(0);

        $this->assertSame('scheduled', $future->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
        // زمان‌بندی‌شده‌ی بدونِ تاریخ سررسید ندارد — نباید منتشر شود (گاردِ whereNotNull فرمان)
        $this->assertSame('scheduled', $noDate->fresh()->status);
    }

    public function test_publish_due_records_the_publish_in_the_activity_log(): void
    {
        // انتشارِ خودکار از Eloquent می‌گذرد، پس هوکِ LogsActivity باید بخورد — دورزدنش
        // یعنی ادمین در Activity Log نمی‌بیند مقاله کِی منتشر شده (قانونِ بخش ۲۰ی CLAUDE.md)
        $due = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->subMinute()]);

        $this->artisan('articles:publish-due')->assertExitCode(0);

        // subject_type با morph map کوتاه ('Article') ذخیره می‌شود، نه نامِ کاملِ کلاس
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'Article',
            'subject_id' => $due->id,
            'description' => 'Article published',
        ]);
    }
}
