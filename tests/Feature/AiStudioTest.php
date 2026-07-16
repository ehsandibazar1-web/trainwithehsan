<?php

namespace Tests\Feature;

use App\Models\AiProfile;
use App\Models\AiPrompt;
use App\Models\AiTemplate;
use App\Models\Article;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiStudioTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ArticleImportService
    {
        return app(ArticleImportService::class);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function validJson(array $overrides = []): string
    {
        return json_encode(array_merge([
            'language' => 'en',
            'title' => 'Studio Article',
            'content' => '<p>Body.</p>',
            'publish_status' => 'published',
        ], $overrides));
    }

    // ---------------------------------------------------------- preview history

    public function test_preview_is_logged_but_saves_no_article(): void
    {
        $result = $this->service()->preview($this->validJson(), 'auto', ['user_id' => $this->owner()->id]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, Article::count());

        $log = ImportLog::first();
        $this->assertSame('previewed', $log->status);
        $this->assertSame('Studio Article', $log->article_title);
    }

    // ---------------------------------------------------------------- rollback

    public function test_rollback_deletes_article_and_stamps_log(): void
    {
        $user = $this->owner();
        $import = $this->service()->import($this->validJson());
        $log = $import['log'];

        $this->assertTrue($log->canRollBack());

        $result = $this->service()->rollback($log, ['user_id' => $user->id]);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, Article::count());

        $log->refresh();
        $this->assertNotNull($log->rolled_back_at);
        $this->assertSame($user->id, $log->rolled_back_by);
        $this->assertSame('Studio Article', $log->article_title); // عنوان برای تاریخچه می‌ماند
        $this->assertFalse($log->canRollBack());

        // حذف از طریق Eloquent → در Activity Log ثبت شده
        $this->assertDatabaseHas('activity_log', ['description' => 'Article deleted']);
    }

    public function test_rollback_refuses_second_run_and_failed_logs(): void
    {
        $log = $this->service()->import($this->validJson())['log'];
        $this->service()->rollback($log);
        $this->assertFalse($this->service()->rollback($log->refresh())['ok']);

        $failedLog = $this->service()->import('{"language":"en"}')['log'];
        $this->assertFalse($this->service()->rollback($failedLog)['ok']);
    }

    // ---------------------------------------------------------- profile defaults

    public function test_profile_defaults_fill_only_missing_fields(): void
    {
        $profile = AiProfile::create([
            'name' => 'Claude EN', 'provider' => 'claude',
            'default_language' => 'tr', 'default_status' => 'draft', 'default_category' => 'Guides',
        ]);

        // زبان در محتوا هست (en) → پیش‌فرض tr نباید برنده شود؛ status و category خالی‌اند → پر می‌شوند
        $json = json_encode(['language' => 'en', 'title' => 'With Profile', 'content' => '<p>x</p>']);
        $result = $this->service()->import($json, 'auto', ['ai_provider' => $profile->provider], $profile->importDefaults());

        $article = $result['article'];
        $this->assertSame('en', $article->locale);
        $this->assertSame('draft', $article->status);
        $this->assertSame('Guides', $article->category);
        $this->assertSame('claude', ImportLog::first()->ai_provider);
    }

    public function test_payload_provider_wins_over_context_provider(): void
    {
        $this->service()->import($this->validJson(['provider' => 'chatgpt']), 'auto', ['ai_provider' => 'claude']);

        $this->assertSame('chatgpt', ImportLog::first()->ai_provider);
    }

    // ---------------------------------------------------------- admin pages

    public function test_import_history_page_renders_with_stats(): void
    {
        $this->service()->import($this->validJson());

        $this->actingAs($this->owner())
            ->get('/admin/import-logs')
            ->assertOk()
            ->assertSee('Import History')
            ->assertSee('Studio Article');
    }

    public function test_draft_queue_lists_imported_drafts_only(): void
    {
        // پیش‌نویس ایمپورت‌شده — باید دیده شود
        $this->service()->import($this->validJson(['title' => 'Queued Draft', 'publish_status' => 'draft']));

        // پیش‌نویس دستی (بدون لاگ ایمپورت) — نباید دیده شود
        Article::create([
            'locale' => 'en', 'title' => 'Manual Draft', 'slug' => 'manual-draft',
            'body' => 'x', 'status' => 'draft',
        ]);

        // ایمپورت بازگردانده‌شده — نباید دیده شود
        $rolled = $this->service()->import($this->validJson(['title' => 'Rolled Draft', 'slug' => 'rolled-draft', 'publish_status' => 'draft']));
        $this->service()->rollback($rolled['log']);

        $this->actingAs($this->owner())
            ->get('/admin/draft-queue')
            ->assertOk()
            ->assertSee('Queued Draft')
            ->assertDontSee('Manual Draft')
            ->assertDontSee('Rolled Draft');
    }

    public function test_template_prompt_and_profile_resources_render(): void
    {
        AiTemplate::create(['name' => 'Standard JSON', 'format' => 'json', 'content' => '{}']);
        AiPrompt::create(['name' => 'Full article prompt', 'prompt' => 'Write an article...']);
        AiProfile::create(['name' => 'Claude', 'provider' => 'claude']);

        $user = $this->owner();

        $this->actingAs($user)->get('/admin/ai-templates')->assertOk()->assertSee('Standard JSON');
        $this->actingAs($user)->get('/admin/ai-prompts')->assertOk()->assertSee('Full article prompt');
        $this->actingAs($user)->get('/admin/ai-profiles')->assertOk()->assertSee('Claude');
    }

    public function test_ai_import_page_still_renders_with_pickers(): void
    {
        AiTemplate::create(['name' => 'My Template', 'format' => 'json', 'content' => '{"title":"x"}']);
        AiProfile::create(['name' => 'My Profile', 'provider' => 'claude']);

        $this->actingAs($this->owner())
            ->get('/admin/ai-import')
            ->assertOk()
            ->assertSee('AI Import')
            ->assertSee('Load a saved template')
            ->assertSee('AI profile');
    }
}
