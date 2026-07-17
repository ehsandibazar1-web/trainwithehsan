<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesModuleTest extends TestCase
{
    use RefreshDatabase;

    // اسلاگ پیش‌فرض عمداً «privacy-policy» نیست — این اسلاگ الان توسط مهاجرت seed صفحات
    // Contact/FAQ/Legal (۲۰۲۶_۰۷_۱۷_۰۰۰۰۱۱) واقعاً پر شده و روی هر اجرای RefreshDatabase موجود
    // است؛ استفاده از همون اسلاگ اینجا با UNIQUE(slug, locale) تصادم می‌کرد
    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Test Standalone Page',
            'slug' => 'test-standalone-page',
            'body' => '<p>Sample standalone page body text.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_published_page_renders_on_both_locales(): void
    {
        $en = $this->makePage();
        $this->makePage([
            'locale' => 'tr',
            'title' => 'Örnek Sayfa',
            'slug' => 'ornek-sayfa',
            'translation_of' => $en->id,
        ]);

        $this->get('/test-standalone-page')->assertOk()->assertSee('Sample standalone page body text');
        $this->get('/tr/ornek-sayfa')->assertOk()->assertSee('Örnek Sayfa');
    }

    public function test_draft_and_future_scheduled_pages_are_hidden(): void
    {
        $this->makePage(['slug' => 'secret', 'status' => 'draft', 'published_at' => null]);
        $this->makePage(['slug' => 'future', 'status' => 'scheduled', 'published_at' => now()->addDay()]);

        $this->get('/secret')->assertNotFound();
        $this->get('/future')->assertNotFound();
    }

    public function test_due_scheduled_page_is_visible_even_before_cron_flips_it(): void
    {
        $this->makePage(['slug' => 'due', 'status' => 'scheduled', 'published_at' => now()->subHour()]);

        $this->get('/due')->assertOk();
    }

    public function test_pages_never_appear_in_blog_home_or_feed_but_do_appear_in_sitemap(): void
    {
        $this->makePage(['title' => 'Standalone Page Title', 'slug' => 'standalone-page']);

        Article::create([
            'locale' => 'en', 'title' => 'A Blog Article', 'slug' => 'a-blog-article',
            'body' => '<p>body</p>', 'excerpt' => 'excerpt', 'author_name' => 'Ehsan Dibazar',
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);

        foreach (['/blog', '/', '/feed'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('Standalone Page Title')
                ->assertDontSee('standalone-page');
        }

        $this->get('/blog')->assertSee('A Blog Article');
        // صفحات مستقل منتشرشده الان واقعاً در سایت‌مپ هستند — تغییر عمدی (نگاه کنید به
        // SeoController::sitemap()) که پوشش سایت‌مپ را برای Contact/FAQ/Legal کامل می‌کند
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('a-blog-article')
            ->assertSee('standalone-page');
    }

    // ۲۰۲۶-۰۷-۱۸: blog.blade.php/tr/blog.blade.php حالا یک CollectionPage/ItemList JSON-LD
    // دارند (قبلاً هیچ schema نداشتند — تنها شکاف باقی‌مانده در SeoAuditService::missingSchema())
    public function test_blog_index_emits_collection_page_schema_for_both_locales(): void
    {
        Article::create([
            'locale' => 'en', 'title' => 'A Blog Article', 'slug' => 'a-blog-article',
            'body' => '<p>body</p>', 'excerpt' => 'excerpt', 'author_name' => 'Ehsan Dibazar',
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);
        Article::create([
            'locale' => 'tr', 'title' => 'Bir Blog Makalesi', 'slug' => 'bir-blog-makalesi',
            'body' => '<p>body</p>', 'excerpt' => 'excerpt', 'author_name' => 'Ehsan Dibazar',
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);

        $this->get('/blog')->assertOk()
            ->assertSee('"@type": "CollectionPage"', false)
            ->assertSee('a-blog-article');

        $this->get('/tr/blog')->assertOk()
            ->assertSee('"@type": "CollectionPage"', false)
            ->assertSee('bir-blog-makalesi');
    }

    public function test_reserved_routes_are_not_shadowed_by_the_page_catchall(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/blog')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/feed')->assertOk();
    }

    public function test_admin_pages_resource_requires_auth_and_renders(): void
    {
        $this->get('/admin/pages')->assertRedirect();

        // User::canAccessPanel فقط ایمیل مالک سایت را می‌پذیرد — تست باید با همان ایمیل وارد شود
        $user = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($user)->get('/admin/pages')->assertOk();
        $this->actingAs($user)->get('/admin/pages/create')->assertOk();
    }
}
