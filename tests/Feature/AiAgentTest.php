<?php

namespace Tests\Feature;

use App\Jobs\RunAgentAudit;
use App\Jobs\RunAiContentGeneration;
use App\Jobs\TranslateArticleDraft;
use App\Models\AiAuditRun;
use App\Models\AiGeneration;
use App\Models\AiRecommendation;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAgent\AgentAuditService;
use App\Services\AiAgent\AgentFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AiAgentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AgentAuditService
    {
        return app(AgentAuditService::class);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>'.str_repeat('word ', 700).'</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy-'.uniqid(),
            'body' => '<p>'.str_repeat('word ', 400).'</p>',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function findingsFor(array $categorized, string $category): array
    {
        return $categorized[$category];
    }

    // ============ AgentAuditService — individual detectors ============

    public function test_content_refresh_flags_stale_published_content(): void
    {
        $article = $this->makeArticle();
        Article::where('id', $article->id)->update(['updated_at' => now()->subDays(200)]);

        $findings = $this->findingsFor($this->service()->run(), 'content_refresh');

        $this->assertNotEmpty($findings);
        $this->assertSame('field', $findings[0]['fix_type']);
        $this->assertSame('body', $findings[0]['fix_field']);
        $this->assertSame('improve', $findings[0]['fix_mode']);
    }

    public function test_content_refresh_ignores_recently_updated_content(): void
    {
        $this->makeArticle();

        $findings = $this->findingsFor($this->service()->run(), 'content_refresh');

        $this->assertEmpty($findings);
    }

    public function test_missing_internal_links_flags_published_content_with_no_outbound_links(): void
    {
        $article = $this->makeArticle(['body' => '<p>No links here at all, just plain text.</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'missing_internal_links');

        $this->assertNotEmpty($findings);
        $this->assertSame($article->id, $findings[0]['content_id']);
        $this->assertSame('internal_links', $findings[0]['fix_type']);
        $this->assertSame('internal_links', $findings[0]['fix_field']);
    }

    public function test_missing_internal_links_ignores_content_that_already_links_out(): void
    {
        $this->makeArticle(['title' => 'Target', 'slug' => 'target-article']);
        $source = $this->makeArticle(['title' => 'Source', 'slug' => 'source-article', 'body' => '<p>See <a href="/blog/target-article">this</a>.</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'missing_internal_links');

        $this->assertEmpty(collect($findings)->where('content_id', $source->id)->all());
    }

    public function test_missing_faq_flags_long_articles_with_no_faqs(): void
    {
        $article = $this->makeArticle();

        $findings = $this->findingsFor($this->service()->run(), 'missing_faq');

        $this->assertNotEmpty($findings);
        $this->assertSame($article->id, $findings[0]['content_id']);
        $this->assertSame('faq', $findings[0]['fix_field']);
    }

    public function test_missing_faq_never_flags_pages(): void
    {
        $this->makePage(['body' => '<p>'.str_repeat('word ', 400).'</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'missing_faq');

        $this->assertEmpty($findings);
    }

    public function test_missing_cta_flags_content_with_no_call_to_action(): void
    {
        $this->makeArticle(['body' => '<p>Just information, no call to action anywhere.</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'missing_cta');

        $this->assertNotEmpty($findings);
        $this->assertSame('cta', $findings[0]['fix_field']);
    }

    public function test_weak_intro_flags_a_short_first_paragraph(): void
    {
        $this->makeArticle(['body' => '<p>Too short.</p><p>'.str_repeat('word ', 200).'</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'weak_intro');

        $this->assertNotEmpty($findings);
        $this->assertSame('body', $findings[0]['fix_field']);
        $this->assertSame('improve', $findings[0]['fix_mode']);
    }

    public function test_weak_conclusion_flags_a_short_last_paragraph(): void
    {
        $this->makeArticle(['body' => '<p>'.str_repeat('word ', 200).'</p><p>The end.</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'weak_conclusion');

        $this->assertNotEmpty($findings);
    }

    public function test_thin_content_flags_short_published_articles(): void
    {
        $article = $this->makeArticle(['body' => '<p>'.str_repeat('word ', 50).'</p>']);

        $findings = $this->findingsFor($this->service()->run(), 'thin_content');

        $this->assertNotEmpty($findings);
        $this->assertSame($article->id, $findings[0]['content_id']);
        $this->assertSame('expand', $findings[0]['fix_mode']);
    }

    public function test_thin_content_ignores_long_articles(): void
    {
        $this->makeArticle();

        $findings = $this->findingsFor($this->service()->run(), 'thin_content');

        $this->assertEmpty($findings);
    }

    public function test_missing_alt_is_fixable_when_the_media_is_a_records_featured_image(): void
    {
        $article = $this->makeArticle();
        $media = Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/hero.jpg',
            'url' => '/storage/articles/hero.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);
        $article->update(['image_path' => 'articles/hero.jpg']);

        $findings = $this->findingsFor($this->service()->run(), 'missing_alt');

        $fixable = collect($findings)->firstWhere('content_id', $article->id);
        $this->assertNotNull($fixable);
        $this->assertSame('Article', $fixable['content_type']);
        $this->assertSame('field', $fixable['fix_type']);
        $this->assertSame('alt_text', $fixable['fix_field']);
    }

    public function test_missing_alt_is_review_only_for_inline_body_images(): void
    {
        $this->makeArticle(['body' => '<p>Text</p><img src="/storage/inline.jpg">']);

        $findings = $this->findingsFor($this->service()->run(), 'missing_alt');

        $this->assertNotEmpty($findings);
        $this->assertNull($findings[0]['fix_type']);
    }

    public function test_missing_schema_always_flags_pages(): void
    {
        $page = $this->makePage();

        $findings = $this->findingsFor($this->service()->run(), 'missing_schema');

        $this->assertTrue(collect($findings)->contains(fn ($f) => $f['content_id'] === $page->id && $f['content_type'] === 'Page'));
        $this->assertTrue(collect($findings)->every(fn ($f) => $f['fix_type'] === null));
    }

    public function test_poor_seo_flags_content_with_no_seo_fields_set(): void
    {
        $article = $this->makeArticle();

        $findings = $this->findingsFor($this->service()->run(), 'poor_seo');

        $this->assertNotEmpty($findings);
        $this->assertSame($article->id, $findings[0]['content_id']);
        $this->assertSame('field', $findings[0]['fix_type']);
    }

    public function test_poor_seo_ignores_content_with_seo_fields_filled(): void
    {
        $this->makeArticle([
            'seo_title' => 'A great SEO title',
            'meta_description' => str_repeat('a', 60),
            'og_title' => 'OG title',
            'og_description' => 'OG description',
        ]);

        $findings = $this->findingsFor($this->service()->run(), 'poor_seo');

        $this->assertEmpty($findings);
    }

    public function test_image_optimization_flags_oversized_in_use_media_but_not_the_alt_warning(): void
    {
        $article = $this->makeArticle();
        Media::create([
            'original_name' => 'huge.jpg', 'disk' => 'public', 'disk_path' => 'articles/huge.jpg',
            'url' => '/storage/articles/huge.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg',
            'size' => 1000, 'width' => 3000, 'height' => 3000, 'alt_text' => 'Already has ALT text',
        ]);
        $article->update(['image_path' => 'articles/huge.jpg']);

        $findings = $this->findingsFor($this->service()->run(), 'image_optimization');

        $this->assertNotEmpty($findings);
        $this->assertStringContainsString('Oversized', $findings[0]['detail']);
        $this->assertStringNotContainsString('ALT', $findings[0]['detail']);
        $this->assertNull($findings[0]['fix_type']);
    }

    public function test_needs_translation_flags_content_with_no_linked_translation(): void
    {
        $article = $this->makeArticle();

        $findings = $this->findingsFor($this->service()->run(), 'needs_translation');

        $this->assertNotEmpty($findings);
        $this->assertSame($article->id, $findings[0]['content_id']);
        $this->assertSame('translate', $findings[0]['fix_type']);
        $this->assertSame('tr', $findings[0]['fix_mode']);
    }

    public function test_needs_translation_ignores_content_that_already_has_a_translation(): void
    {
        $en = $this->makeArticle();
        $this->makeArticle(['locale' => 'tr', 'slug' => 'tr-version', 'translation_of' => $en->id]);

        $findings = $this->findingsFor($this->service()->run(), 'needs_translation');

        $this->assertEmpty(collect($findings)->where('content_id', $en->id)->all());
    }

    public function test_orphan_pages_reuses_seo_audit_service(): void
    {
        $article = $this->makeArticle();

        $findings = $this->findingsFor($this->service()->run(), 'orphan_pages');

        $this->assertTrue(collect($findings)->contains(fn ($f) => $f['content_id'] === $article->id));
    }

    public function test_duplicate_topics_flags_similar_titles_in_the_same_locale(): void
    {
        $this->makeArticle(['title' => 'Complete Guide To Guard Passing Techniques', 'slug' => 'guide-1']);
        $this->makeArticle(['title' => 'Complete Guide To Guard Passing Basics', 'slug' => 'guide-2']);

        $findings = $this->findingsFor($this->service()->run(), 'duplicate_topics');

        $this->assertNotEmpty($findings);
        $this->assertNull($findings[0]['fix_type']);
        $this->assertNotNull($findings[0]['related_content_id']);
    }

    public function test_duplicate_topics_exempts_translation_pairs_even_in_the_same_locale(): void
    {
        $en = $this->makeArticle(['title' => 'Guard Passing Guide']);
        $this->makeArticle(['title' => 'Guard Passing Guide', 'slug' => 'other-slug', 'translation_of' => $en->id]);

        $findings = $this->findingsFor($this->service()->run(), 'duplicate_topics');

        $this->assertEmpty($findings);
    }

    public function test_content_cannibalization_flags_two_published_articles_sharing_a_keyword(): void
    {
        $a = $this->makeArticle(['title' => 'Article A', 'slug' => 'article-a']);
        $b = $this->makeArticle(['title' => 'Article B', 'slug' => 'article-b']);
        $a->keywords()->create(['keyword' => 'brazilian jiu jitsu']);
        $b->keywords()->create(['keyword' => 'Brazilian Jiu Jitsu']);

        $findings = $this->findingsFor($this->service()->run(), 'content_cannibalization');

        $this->assertCount(1, $findings);
        $this->assertSame('warning', $findings[0]['severity']);
        $this->assertNull($findings[0]['fix_type']);
    }

    public function test_content_cannibalization_ignores_a_single_article_per_keyword(): void
    {
        $a = $this->makeArticle();
        $a->keywords()->create(['keyword' => 'unique keyword']);

        $findings = $this->findingsFor($this->service()->run(), 'content_cannibalization');

        $this->assertEmpty($findings);
    }

    // ============ generateAndPersist — upsert / never touch decided rows ============

    public function test_generate_and_persist_creates_an_audit_run_and_pending_recommendations(): void
    {
        $this->makeArticle();

        $run = $this->service()->generateAndPersist('manual');

        $this->assertSame('completed', $run->status);
        $this->assertSame('manual', $run->trigger_type);
        $this->assertGreaterThan(0, $run->found_count);
        $this->assertGreaterThan(0, AiRecommendation::count());
        $this->assertTrue(AiRecommendation::pending()->exists());
    }

    public function test_generate_and_persist_never_touches_applied_or_rejected_rows(): void
    {
        $this->makeArticle(['body' => '<p>No links here at all.</p>']);
        $this->service()->generateAndPersist('manual');

        $recommendation = AiRecommendation::category('missing_internal_links')->first();
        $recommendation->update(['status' => 'applied']);

        $run2 = $this->service()->generateAndPersist('manual');

        $recommendation->refresh();
        $this->assertSame('applied', $recommendation->status);
        $this->assertSame(0, $run2->new_count);
    }

    public function test_generate_and_persist_resolves_pending_recommendations_that_no_longer_apply(): void
    {
        $other = $this->makeArticle(['title' => 'Other', 'slug' => 'other-article']);
        $article = $this->makeArticle(['body' => '<p>No links here at all.</p>']);
        $this->service()->generateAndPersist('manual');
        $this->assertTrue(AiRecommendation::category('missing_internal_links')->where('content_id', $article->id)->exists());

        $article->update(['body' => '<p>Now it links to <a href="/blog/other-article">a related article</a>.</p>']);
        $run2 = $this->service()->generateAndPersist('manual');

        $this->assertFalse(AiRecommendation::category('missing_internal_links')->where('content_id', $article->id)->where('status', 'pending')->exists());
        $this->assertGreaterThan(0, $run2->resolved_count);
    }

    // ============ AgentFixService ============

    public function test_queue_fix_dispatches_run_ai_content_generation_for_a_field_fix(): void
    {
        Bus::fake();
        $article = $this->makeArticle();

        $recommendation = AiRecommendation::create([
            'category' => 'poor_seo', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'field', 'fix_field' => 'seo_title', 'fix_mode' => 'generate',
        ]);

        $queued = app(AgentFixService::class)->queueFix($recommendation);

        $this->assertTrue($queued);
        $recommendation->refresh();
        $this->assertNotNull($recommendation->ai_generation_id);
        $this->assertSame('seo_title', $recommendation->generation->field);
        Bus::assertDispatched(RunAiContentGeneration::class);
    }

    public function test_queue_fix_dispatches_translate_article_draft_for_a_translate_fix(): void
    {
        Bus::fake();
        $article = $this->makeArticle();

        $recommendation = AiRecommendation::create([
            'category' => 'needs_translation', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'translate', 'fix_field' => 'translate', 'fix_mode' => 'tr',
        ]);

        $queued = app(AgentFixService::class)->queueFix($recommendation);

        $this->assertTrue($queued);
        $recommendation->refresh();
        $this->assertSame('translate', $recommendation->generation->field);
        $this->assertSame('tr', $recommendation->generation->mode);
        Bus::assertDispatched(TranslateArticleDraft::class);
    }

    public function test_queue_fix_returns_false_for_a_review_only_recommendation(): void
    {
        $article = $this->makeArticle();

        $recommendation = AiRecommendation::create([
            'category' => 'orphan_pages', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => null,
        ]);

        $this->assertFalse(app(AgentFixService::class)->queueFix($recommendation));
        $this->assertNull($recommendation->fresh()->ai_generation_id);
    }

    public function test_approve_fix_writes_the_generated_value_via_generation_applier(): void
    {
        $article = $this->makeArticle(['seo_title' => null]);
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate',
            'status' => 'completed', 'result' => 'A New SEO Title',
        ]);
        $recommendation = AiRecommendation::create([
            'category' => 'poor_seo', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'field', 'fix_field' => 'seo_title', 'fix_mode' => 'generate',
            'ai_generation_id' => $generation->id,
        ]);

        $applied = app(AgentFixService::class)->approveFix($recommendation);

        $this->assertTrue($applied);
        $this->assertSame('A New SEO Title', $article->fresh()->seo_title);
        $this->assertSame('applied', $recommendation->fresh()->status);
        $this->assertNotNull($recommendation->fresh()->reviewed_at);
    }

    public function test_approve_fix_returns_false_while_the_generation_is_still_pending(): void
    {
        $article = $this->makeArticle();
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate', 'status' => 'queued',
        ]);
        $recommendation = AiRecommendation::create([
            'category' => 'poor_seo', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'field', 'fix_field' => 'seo_title', 'fix_mode' => 'generate',
            'ai_generation_id' => $generation->id,
        ]);

        $this->assertFalse(app(AgentFixService::class)->approveFix($recommendation));
        $this->assertSame('pending', $recommendation->fresh()->status);
    }

    public function test_approve_fix_for_internal_links_creates_a_pending_ai_origin_suggestion(): void
    {
        $source = $this->makeArticle(['title' => 'Source']);
        $target = $this->makeArticle(['title' => 'Target', 'slug' => 'target-x']);

        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $source->id, 'field' => 'internal_links', 'mode' => 'generate',
            'status' => 'completed',
            'result' => [['id' => $target->id, 'type' => 'Article', 'anchor_text' => 'this article', 'reason' => 'related']],
        ]);
        $recommendation = AiRecommendation::create([
            'category' => 'missing_internal_links', 'content_type' => 'Article', 'content_id' => $source->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'internal_links', 'fix_field' => 'internal_links', 'fix_mode' => 'generate',
            'ai_generation_id' => $generation->id,
        ]);

        $applied = app(AgentFixService::class)->approveFix($recommendation);

        $this->assertTrue($applied);
        $this->assertDatabaseHas('internal_link_suggestions', [
            'source_type' => 'Article', 'source_id' => $source->id,
            'target_type' => 'Article', 'target_id' => $target->id,
            'status' => 'pending', 'origin' => 'ai',
        ]);
    }

    public function test_approve_fix_for_translate_marks_resolved_without_writing_to_the_source_record(): void
    {
        $article = $this->makeArticle();
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'translate', 'mode' => 'tr',
            'status' => 'completed', 'result' => ['id' => 999, 'type' => 'Article', 'title' => 'Translated'],
        ]);
        $recommendation = AiRecommendation::create([
            'category' => 'needs_translation', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'translate', 'fix_field' => 'translate', 'fix_mode' => 'tr',
            'ai_generation_id' => $generation->id,
        ]);

        $applied = app(AgentFixService::class)->approveFix($recommendation);

        $this->assertTrue($applied);
        $this->assertSame('applied', $recommendation->fresh()->status);
        $this->assertNull($article->fresh()->seo_title); // نوشتنی روی رکورد اصلی اتفاق نیفتاده
    }

    public function test_reject_fix_dismisses_a_review_only_recommendation_without_a_generation(): void
    {
        $article = $this->makeArticle();
        $recommendation = AiRecommendation::create([
            'category' => 'orphan_pages', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => null,
        ]);

        app(AgentFixService::class)->rejectFix($recommendation);

        $recommendation->refresh();
        $this->assertSame('rejected', $recommendation->status);
        $this->assertNotNull($recommendation->reviewed_at);
    }

    // ============ agent:audit command + queued job ============

    public function test_agent_audit_command_runs_a_synchronous_audit(): void
    {
        $this->makeArticle();

        $this->artisan('agent:audit')->assertSuccessful();

        $this->assertSame(1, AiAuditRun::count());
        $this->assertSame('scheduled', AiAuditRun::first()->trigger_type);
    }

    public function test_run_agent_audit_job_calls_the_same_service(): void
    {
        $this->makeArticle();

        (new RunAgentAudit)->handle(app(AgentAuditService::class));

        $run = AiAuditRun::first();
        $this->assertSame('manual', $run->trigger_type);
        $this->assertSame('completed', $run->status);
    }
}
