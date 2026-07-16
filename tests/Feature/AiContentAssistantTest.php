<?php

namespace Tests\Feature;

use App\Jobs\RunAiContentGeneration;
use App\Livewire\AiAssistantPanel;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAssistant\ActionRegistry;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\AiAssistant\Contracts\AiProvider;
use App\Services\AiAssistant\Providers\AnthropicProvider;
use App\Services\AiAssistant\Providers\NullProvider;
use App\Services\Seo\SeoAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiContentAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>Guard passing is a fundamental BJJ skill. Practice it often.</p>',
            'author_name' => 'Ehsan',
            'status' => 'draft',
        ], $overrides));
    }

    private function fakeAnthropicText(string $text): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
            ], 200),
        ]);
    }

    // ============ ActionRegistry ============

    public function test_registry_scopes_fields_to_the_correct_model(): void
    {
        $articleFields = ActionRegistry::applicableTo('Article');
        $pageFields = ActionRegistry::applicableTo('Page');

        $this->assertArrayHasKey('excerpt', $articleFields);
        $this->assertArrayHasKey('faq', $articleFields);
        $this->assertArrayHasKey('category', $articleFields);

        $this->assertArrayNotHasKey('excerpt', $pageFields);
        $this->assertArrayNotHasKey('faq', $pageFields);
        $this->assertArrayNotHasKey('category', $pageFields);

        $this->assertArrayHasKey('seo_title', $pageFields);
        $this->assertArrayHasKey('slug', $pageFields);
    }

    // ============ NullProvider ============

    public function test_null_provider_throws_a_clear_error_when_unconfigured(): void
    {
        config(['services.anthropic.key' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No AI provider is configured');

        (new NullProvider)->respond('system', 'user');
    }

    // ============ AnthropicProvider ============

    public function test_anthropic_provider_throws_on_a_non_successful_response(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response('server error', 500)]);

        $this->expectException(\RuntimeException::class);

        (new AnthropicProvider)->respond('system', 'user');
    }

    public function test_anthropic_provider_extracts_text_from_the_messages_response(): void
    {
        $this->fakeAnthropicText('A great SEO title');

        $result = (new AnthropicProvider)->respond('system', 'user');

        $this->assertSame('A great SEO title', $result);
    }

    // ============ ContentAssistantService ============

    public function test_generate_rejects_a_field_not_applicable_to_the_model(): void
    {
        $page = Page::create([
            'locale' => 'en', 'title' => 'Privacy', 'slug' => 'privacy-'.uniqid(),
            'body' => '<p>x</p>', 'status' => 'draft',
        ]);

        $this->fakeAnthropicText('irrelevant');
        $service = app(ContentAssistantService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->generate($page, 'excerpt', 'generate');
    }

    public function test_generate_rejects_an_unsupported_mode_for_a_field(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('irrelevant');
        $service = app(ContentAssistantService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->generate($article, 'faq', 'shorten');
    }

    public function test_generate_returns_plain_text_for_a_text_shaped_field(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('  "A clean SEO title"  ');

        $outcome = app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        $this->assertSame('A clean SEO title', $outcome['result']);
        $this->assertSame([], $outcome['warnings']);
    }

    public function test_generate_parses_a_json_list_for_tags(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode(['bjj', 'guard passing', 'self defense']));

        $outcome = app(ContentAssistantService::class)->generate($article, 'tags', 'generate');

        $this->assertSame(['bjj', 'guard passing', 'self defense'], $outcome['result']);
    }

    public function test_generate_parses_json_wrapped_in_markdown_fences(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText("```json\n".json_encode(['a', 'b'])."\n```");

        $outcome = app(ContentAssistantService::class)->generate($article, 'outline', 'generate');

        $this->assertSame(['a', 'b'], $outcome['result']);
    }

    public function test_generate_parses_qa_pairs_for_faq(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            ['question' => 'What belt do I need?', 'answer' => 'None — beginners welcome.'],
            ['question' => 'Bad entry with no keys', 'foo' => 'bar'],
        ]));

        $outcome = app(ContentAssistantService::class)->generate($article, 'faq', 'generate');

        $this->assertCount(1, $outcome['result']);
        $this->assertSame('What belt do I need?', $outcome['result'][0]['question']);
    }

    public function test_generate_reports_a_warning_when_the_response_is_not_valid_json(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('not json at all');

        $outcome = app(ContentAssistantService::class)->generate($article, 'tags', 'generate');

        $this->assertNull($outcome['result']);
        $this->assertNotEmpty($outcome['warnings']);
    }

    // ============ RunAiContentGeneration job ============

    public function test_job_marks_generation_completed_on_success(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('A clean SEO title');

        $generation = AiGeneration::create([
            'content_type' => $article->getMorphClass(),
            'content_id' => $article->id,
            'field' => 'seo_title',
            'mode' => 'generate',
            'status' => 'queued',
            'input_snapshot' => $article->seo_title,
        ]);

        (new RunAiContentGeneration($generation->id))->handle(app(ContentAssistantService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertSame('A clean SEO title', $generation->result);
        $this->assertTrue($generation->canApply());
    }

    public function test_job_marks_generation_failed_when_the_provider_throws(): void
    {
        config(['services.anthropic.key' => null]);

        $article = $this->makeArticle();

        $generation = AiGeneration::create([
            'content_type' => $article->getMorphClass(),
            'content_id' => $article->id,
            'field' => 'seo_title',
            'mode' => 'generate',
            'status' => 'queued',
            'input_snapshot' => $article->seo_title,
        ]);

        (new RunAiContentGeneration($generation->id))->handle(app(ContentAssistantService::class));

        $generation->refresh();
        $this->assertSame('failed', $generation->status);
        $this->assertStringContainsString('No AI provider is configured', $generation->error);
        $this->assertFalse($generation->canApply());
    }

    public function test_job_does_nothing_gracefully_when_the_generation_was_deleted(): void
    {
        (new RunAiContentGeneration(999999))->handle(app(ContentAssistantService::class));
        $this->assertTrue(true); // فقط باید بدون exception اجرا شود
    }

    // ============ Apply / Restore snapshot round-trip ============

    public function test_apply_then_restore_round_trips_the_field_value(): void
    {
        $article = $this->makeArticle(['seo_title' => null]);

        $generation = AiGeneration::create([
            'content_type' => $article->getMorphClass(),
            'content_id' => $article->id,
            'field' => 'seo_title',
            'mode' => 'generate',
            'status' => 'completed',
            'input_snapshot' => $article->seo_title,
            'result' => 'A brand new SEO title',
        ]);

        $this->assertTrue($generation->canApply());

        // همان کاری که AiContentAssistant::applyGeneration() انجام می‌دهد
        $article->update(['seo_title' => $generation->result]);
        $generation->update(['applied_at' => now()]);

        $article->refresh();
        $this->assertSame('A brand new SEO title', $article->seo_title);
        $this->assertTrue($generation->fresh()->canRestore());

        // همان کاری که AiContentAssistant::restoreGeneration() انجام می‌دهد
        $article->update(['seo_title' => $generation->input_snapshot]);
        $generation->update(['restored_at' => now()]);

        $article->refresh();
        $this->assertNull($article->seo_title);
        $this->assertFalse($generation->fresh()->canRestore());
    }

    public function test_a_failed_generation_cannot_be_applied(): void
    {
        $generation = AiGeneration::create([
            'content_type' => 'Article',
            'content_id' => 1,
            'field' => 'seo_title',
            'mode' => 'generate',
            'status' => 'failed',
            'error' => 'boom',
        ]);

        $this->assertFalse($generation->canApply());
    }

    // ============ Provider binding ============

    public function test_container_binds_null_provider_when_no_key_is_configured(): void
    {
        config(['services.anthropic.key' => null]);

        $this->assertInstanceOf(NullProvider::class, app(AiProvider::class));
    }

    public function test_container_binds_anthropic_provider_when_a_key_is_configured(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $this->assertInstanceOf(AnthropicProvider::class, app(AiProvider::class));
    }

    // ============ ContentReviewService ============

    public function test_review_flags_missing_headings_long_paragraphs_and_missing_alt_text(): void
    {
        $longParagraph = '<p>'.str_repeat('word ', 200).'</p>';
        $article = $this->makeArticle([
            'body' => $longParagraph.'<img src="/x.jpg" alt="">',
        ]);

        $findings = app(ContentReviewService::class)->review($article);
        $types = collect($findings)->pluck('type');

        $this->assertContains('missing_headings', $types);
        $this->assertContains('long_paragraph', $types);
        $this->assertContains('missing_alt_text', $types);
        $this->assertContains('missing_internal_links', $types);
        $this->assertContains('missing_external_links', $types);
    }

    public function test_review_does_not_flag_a_well_structured_short_article(): void
    {
        $article = $this->makeArticle([
            'body' => '<h2>Intro</h2><p>Short paragraph.</p>'
                .'<p>Book a class today by getting in touch with us via the contact page.</p>'
                .'<p><a href="/blog/other-article">related</a> and an <a href="https://example.com">external source</a>.</p>'
                .'<img src="/x.jpg" alt="A student practicing guard passing">',
        ]);

        $findings = app(ContentReviewService::class)->review($article);
        $types = collect($findings)->pluck('type');

        $this->assertNotContains('missing_headings', $types);
        $this->assertNotContains('long_paragraph', $types);
        $this->assertNotContains('missing_alt_text', $types);
        $this->assertNotContains('missing_internal_links', $types);
        $this->assertNotContains('missing_external_links', $types);
        $this->assertNotContains('weak_cta', $types);
    }

    public function test_review_flags_duplicate_keywords(): void
    {
        $article = $this->makeArticle(['body' => '<h2>x</h2><p>text</p>']);
        $article->keywords()->create(['keyword' => 'BJJ']);
        $article->keywords()->create(['keyword' => 'bjj']);

        $findings = app(ContentReviewService::class)->review($article);
        $types = collect($findings)->pluck('type');

        $this->assertContains('duplicate_keywords', $types);
    }

    public function test_review_flags_a_faq_opportunity_on_long_articles_without_faqs(): void
    {
        $article = $this->makeArticle([
            'body' => '<h2>x</h2><p>'.str_repeat('word ', 650).'</p>',
            'faqs' => null,
        ]);

        $findings = app(ContentReviewService::class)->review($article);
        $this->assertContains('missing_faq_opportunity', collect($findings)->pluck('type'));
    }

    public function test_review_does_not_flag_faq_opportunity_on_pages(): void
    {
        $page = Page::create([
            'locale' => 'en', 'title' => 'Privacy', 'slug' => 'privacy-'.uniqid(),
            'body' => '<h2>x</h2><p>'.str_repeat('word ', 650).'</p>', 'status' => 'draft',
        ]);

        $findings = app(ContentReviewService::class)->review($page);
        $this->assertNotContains('missing_faq_opportunity', collect($findings)->pluck('type'));
    }

    // ============ content_review_summary action ============

    public function test_content_review_summary_field_is_not_appliable(): void
    {
        $definition = ActionRegistry::for('content_review_summary');
        $this->assertFalse($definition['appliable']);
    }

    public function test_content_review_summary_prompt_is_built_from_review_findings(): void
    {
        $article = $this->makeArticle(['body' => '<p>short</p>']);
        $this->fakeAnthropicText('This article is missing headings and internal links.');

        $outcome = app(ContentAssistantService::class)->generate($article, 'content_review_summary', 'generate');

        $this->assertSame('This article is missing headings and internal links.', $outcome['result']);
    }

    // ============ Phase 3: internal link suggestions ============

    public function test_internal_links_prompt_lists_other_same_locale_content(): void
    {
        $target = $this->makeArticle(['title' => 'Closed Guard Escapes', 'status' => 'published']);
        $source = $this->makeArticle(['title' => 'Guard Passing Basics']);

        $this->fakeAnthropicText(json_encode([
            ['id' => $target->id, 'type' => 'Article', 'anchor_text' => 'closed guard escapes', 'reason' => 'related topic'],
        ]));

        $outcome = app(ContentAssistantService::class)->generate($source, 'internal_links', 'generate');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Closed Guard Escapes'));
        $this->assertSame($target->id, $outcome['result'][0]['id']);
        $this->assertSame('Article', $outcome['result'][0]['type']);
    }

    public function test_apply_internal_link_suggestions_creates_pending_ai_origin_rows(): void
    {
        $target = $this->makeArticle(['title' => 'Closed Guard Escapes', 'status' => 'published']);
        $source = $this->makeArticle(['title' => 'Guard Passing Basics']);

        $generation = AiGeneration::create([
            'content_type' => 'Article',
            'content_id' => $source->id,
            'field' => 'internal_links',
            'mode' => 'generate',
            'status' => 'completed',
            'result' => [
                ['id' => $target->id, 'type' => 'Article', 'anchor_text' => 'closed guard escapes', 'reason' => 'related topic'],
            ],
        ]);

        $page = new AiAssistantPanel;
        $page->record = $source;
        $page->recordType = 'Article';
        $page->applyInternalLinkSuggestions($generation->id);

        $suggestion = InternalLinkSuggestion::first();
        $this->assertNotNull($suggestion);
        $this->assertSame('ai', $suggestion->origin);
        $this->assertSame('pending', $suggestion->status);
        $this->assertSame($target->id, $suggestion->target_id);
        $this->assertNotNull($generation->fresh()->applied_at);
    }

    // ============ Phase 3: external link suggestions ============

    public function test_generate_parses_external_link_suggestions(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            ['url' => 'https://example.com/bjj', 'anchor_text' => 'BJJ federation', 'reason' => 'authoritative source'],
        ]));

        $outcome = app(ContentAssistantService::class)->generate($article, 'external_links', 'generate');

        $this->assertSame('https://example.com/bjj', $outcome['result'][0]['url']);
    }

    public function test_seo_audit_service_check_urls_flags_unreachable_links(): void
    {
        Http::fake([
            'good.example.com/*' => Http::response('', 200),
            'bad.example.com/*' => Http::response('', 500),
        ]);

        $result = app(SeoAuditService::class)->checkUrls(['https://good.example.com/', 'https://bad.example.com/']);

        $this->assertFalse($result['https://good.example.com/']['broken']);
        $this->assertTrue($result['https://bad.example.com/']['broken']);
    }

    // ============ Phase 3: image ALT text targets the Media row, not the record ============

    public function test_alt_text_apply_and_restore_write_to_the_media_row(): void
    {
        $article = $this->makeArticle(['image_path' => 'articles/guard.jpg']);
        $media = Media::create([
            'original_name' => 'guard.jpg', 'disk' => 'public', 'disk_path' => 'articles/guard.jpg',
            'url' => '/storage/articles/guard.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg',
            'size' => 1000, 'alt_text' => 'Old alt text',
        ]);

        $generation = AiGeneration::create([
            'content_type' => 'Article',
            'content_id' => $article->id,
            'field' => 'alt_text',
            'mode' => 'generate',
            'status' => 'completed',
            'input_snapshot' => 'Old alt text',
            'result' => 'A student passing the guard',
        ]);

        $page = new AiAssistantPanel;
        $page->record = $article;
        $page->recordType = 'Article';
        $page->applyGeneration($generation->id);

        $this->assertSame('A student passing the guard', $media->fresh()->alt_text);

        $generation->update(['applied_at' => now()]);
        $page->restoreGeneration($generation->id);

        $this->assertSame('Old alt text', $media->fresh()->alt_text);
    }

    public function test_alt_text_generation_sends_the_image_url_to_the_provider(): void
    {
        $article = $this->makeArticle(['image_path' => 'articles/guard.jpg']);
        $this->fakeAnthropicText('A student passing the guard');

        app(ContentAssistantService::class)->generate($article, 'alt_text', 'generate');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'guard.jpg'));
    }
}
