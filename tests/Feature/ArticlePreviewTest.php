<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * اولویت ۵ی Testing Strategy: PreviewController — لینکِ امضاشده تنها راهِ دیدنِ محتوای
 * منتشرنشده است؛ بدونِ امضای معتبر رد می‌شود، با امضا حتی پیش‌نویس هم رندر می‌شود.
 */
class ArticlePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Preview Target Article',
            'slug' => 'preview-target-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'author_name' => 'Ehsan',
            'status' => 'draft',
            'published_at' => null,
        ], $overrides));
    }

    public function test_unsigned_preview_requests_are_rejected(): void
    {
        $draft = $this->makeArticle();

        $this->get('/preview/article/'.$draft->id)->assertForbidden();
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $draft = $this->makeArticle();
        $url = URL::signedRoute('articles.preview', ['article' => $draft->id]);

        $this->get($url.'x')->assertForbidden();
    }

    public function test_signed_preview_renders_a_draft_article(): void
    {
        $draft = $this->makeArticle();

        $this->get(URL::signedRoute('articles.preview', ['article' => $draft->id]))
            ->assertOk()
            ->assertSee($draft->title);
    }

    public function test_signed_preview_renders_a_future_scheduled_article(): void
    {
        $future = $this->makeArticle(['status' => 'scheduled', 'published_at' => now()->addWeek()]);

        $this->get(URL::signedRoute('articles.preview', ['article' => $future->id]))
            ->assertOk()
            ->assertSee($future->title);
    }

    public function test_signed_preview_renders_the_turkish_template_for_a_turkish_article(): void
    {
        $tr = $this->makeArticle(['locale' => 'tr', 'title' => 'Türkçe Önizleme Makalesi']);

        // قالبِ tr.blog-post زبانِ صفحه را tr می‌گذارد — یعنی مسیرِ درستِ نما انتخاب شده
        $this->get(URL::signedRoute('articles.preview', ['article' => $tr->id]))
            ->assertOk()
            ->assertSee($tr->title)
            ->assertSee('lang="tr"', false);
    }
}
