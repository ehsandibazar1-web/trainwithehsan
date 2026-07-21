<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * باکسِ نویسنده‌ی پایانِ مقاله — از 2026-07-21 متن‌هایش از CMS می‌آید (About Page Settings ←
 * Author box، کلیدهای about.{locale}.author_box_*)؛ تنظیم‌نشده = همان کپیِ پیش‌فرضِ داخلِ قالب،
 * پس نصبِ دست‌نخورده بایت‌به‌بایت مثلِ قبل رندر می‌شود.
 */
class AuthorBoxTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Author Box Article',
            'slug' => 'author-box-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_the_default_copy_renders_when_nothing_is_configured(): void
    {
        $article = $this->makeArticle();

        $this->get('/blog/'.$article->slug)->assertOk()
            ->assertSee("Hi, I'm Ehsan Dibazar")
            ->assertSee('Learn more about Ehsan Dibazar');
    }

    public function test_configured_texts_replace_the_defaults(): void
    {
        SiteSetting::set('about.en.author_box_title', 'Custom Author Title', 'about');
        SiteSetting::set('about.en.author_box_subtitle', 'Custom Author Subtitle', 'about');
        // خطِ خالی وسطِ متن باید دو پاراگرافِ جدا بسازد
        SiteSetting::set('about.en.author_box_text', "First custom paragraph.\n\nSecond custom paragraph.", 'about');
        SiteSetting::set('about.en.author_box_button_text', 'Custom Button Label', 'about');

        $article = $this->makeArticle();

        $this->get('/blog/'.$article->slug)->assertOk()
            ->assertSee('Custom Author Title')
            ->assertSee('Custom Author Subtitle')
            ->assertSee('<p>First custom paragraph.</p>', false)
            ->assertSee('<p>Second custom paragraph.</p>', false)
            ->assertSee('Custom Button Label')
            ->assertDontSee("Hi, I'm Ehsan Dibazar")
            ->assertDontSee('Learn more about Ehsan Dibazar');
    }

    public function test_locales_are_independent(): void
    {
        // فقط انگلیسی تنظیم شده — نسخه‌ی ترکی باید پیش‌فرضِ ترکیِ خودش را نگه دارد
        SiteSetting::set('about.en.author_box_title', 'English Only Title', 'about');

        $tr = $this->makeArticle(['locale' => 'tr']);

        $this->get('/tr/blog/'.$tr->slug)->assertOk()
            ->assertSee('Merhaba, ben Ehsan Dibazar')
            ->assertDontSee('English Only Title');
    }

    public function test_a_blank_saved_value_still_falls_back_to_the_default(): void
    {
        // ذخیره‌ی فرمِ دست‌نخورده مقدارِ '' می‌نویسد — نباید عنوانِ خالی رندر شود
        SiteSetting::set('about.en.author_box_title', '', 'about');

        $article = $this->makeArticle();

        $this->get('/blog/'.$article->slug)->assertOk()
            ->assertSee("Hi, I'm Ehsan Dibazar");
    }

    public function test_the_signed_preview_shows_the_same_configured_texts(): void
    {
        SiteSetting::set('about.en.author_box_title', 'Preview Author Title', 'about');

        $draft = $this->makeArticle(['status' => 'draft', 'published_at' => null]);

        $this->get(URL::signedRoute('articles.preview', ['article' => $draft->id]))
            ->assertOk()
            ->assertSee('Preview Author Title');
    }
}
