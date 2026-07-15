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

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'body' => '<p>Privacy body text.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_published_page_renders_on_both_locales(): void
    {
        $en = $this->makePage();
        $this->makePage([
            'locale' => 'tr',
            'title' => 'Gizlilik Politikası',
            'slug' => 'gizlilik-politikasi',
            'translation_of' => $en->id,
        ]);

        $this->get('/privacy-policy')->assertOk()->assertSee('Privacy body text');
        $this->get('/tr/gizlilik-politikasi')->assertOk()->assertSee('Gizlilik Politikası');
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

    public function test_pages_never_appear_in_blog_home_feed_or_sitemap(): void
    {
        $this->makePage(['title' => 'Standalone Page Title', 'slug' => 'standalone-page']);

        Article::create([
            'locale' => 'en', 'title' => 'A Blog Article', 'slug' => 'a-blog-article',
            'body' => '<p>body</p>', 'excerpt' => 'excerpt', 'author_name' => 'Ehsan Dibazar',
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);

        foreach (['/blog', '/', '/feed', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('Standalone Page Title')
                ->assertDontSee('standalone-page');
        }

        $this->get('/blog')->assertSee('A Blog Article');
        $this->get('/sitemap.xml')->assertSee('a-blog-article');
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
