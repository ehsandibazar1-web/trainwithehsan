<?php

namespace Tests\Feature;

use App\Jobs\RunAiContentGeneration;
use App\Livewire\AiAssistantPanel;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAssistant\ActionRegistry;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\AiAssistant\DiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>Guard passing is a fundamental BJJ skill.</p>',
            'author_name' => 'Ehsan',
            'status' => 'draft',
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy-'.uniqid(),
            'body' => '<p>Some page content.</p>',
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

    private function makePanel(Article|Page $record): AiAssistantPanel
    {
        $panel = new AiAssistantPanel;
        $panel->record = $record;
        $panel->recordType = $record instanceof Article ? 'Article' : 'Page';

        return $panel;
    }

    // ============ DiffService ============

    public function test_diff_words_marks_identical_text_as_unchanged(): void
    {
        $diff = app(DiffService::class)->diffWords('Guard Passing Basics', 'Guard Passing Basics');

        $this->assertCount(1, $diff);
        $this->assertSame('same', $diff[0]['type']);
        $this->assertSame('Guard Passing Basics', $diff[0]['text']);
    }

    public function test_diff_words_detects_additions_and_removals(): void
    {
        $diff = app(DiffService::class)->diffWords('Old Title Here', 'New Title Here');

        $types = collect($diff)->pluck('type')->all();

        $this->assertContains('del', $types);
        $this->assertContains('add', $types);
        $this->assertContains('same', $types);

        $del = collect($diff)->firstWhere('type', 'del');
        $add = collect($diff)->firstWhere('type', 'add');

        $this->assertStringContainsString('Old', $del['text']);
        $this->assertStringContainsString('New', $add['text']);
    }

    public function test_diff_words_handles_blank_old_value_as_pure_addition(): void
    {
        $diff = app(DiffService::class)->diffWords(null, 'Brand New Text');

        $this->assertCount(1, $diff);
        $this->assertSame('add', $diff[0]['type']);
        $this->assertSame('Brand New Text', $diff[0]['text']);
    }

    public function test_diff_words_strips_html_tags_before_diffing(): void
    {
        $diff = app(DiffService::class)->diffWords('<p>Hello world</p>', 'Hello world');

        $this->assertCount(1, $diff);
        $this->assertSame('same', $diff[0]['type']);
    }

    // ============ ContentReviewService::scoreCard ============

    public function test_score_card_returns_all_six_categories_and_an_overall_average(): void
    {
        $article = $this->makeArticle();

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertArrayHasKey('overall', $card);
        $this->assertArrayHasKey('categories', $card);
        $this->assertEqualsCanonicalizing(
            ['seo', 'readability', 'content_quality', 'internal_linking', 'media_optimization', 'schema'],
            array_keys($card['categories'])
        );

        $average = (int) round(collect($card['categories'])->avg(fn (array $c) => $c['score']));
        $this->assertSame($average, $card['overall']);
    }

    public function test_score_card_seo_category_rewards_filled_seo_fields(): void
    {
        $bare = $this->makeArticle();
        $filled = $this->makeArticle([
            'seo_title' => 'A Great SEO Title',
            'meta_description' => str_repeat('a', 60),
            'og_title' => 'OG Title',
            'og_description' => 'OG Description',
        ]);

        $card = app(ContentReviewService::class)->scoreCard($filled);
        $bareCard = app(ContentReviewService::class)->scoreCard($bare);

        $this->assertSame(100, $card['categories']['seo']['score']);
        $this->assertSame(0, $bareCard['categories']['seo']['score']);
        $this->assertNotEmpty($bareCard['categories']['seo']['issues']);
    }

    public function test_score_card_schema_is_perfect_for_articles_and_capped_for_pages(): void
    {
        $article = $this->makeArticle();
        $page = $this->makePage();

        $articleCard = app(ContentReviewService::class)->scoreCard($article);
        $pageCard = app(ContentReviewService::class)->scoreCard($page);

        $this->assertSame(100, $articleCard['categories']['schema']['score']);
        $this->assertSame(40, $pageCard['categories']['schema']['score']);
        $this->assertNotEmpty($pageCard['categories']['schema']['issues']);
    }

    public function test_score_card_internal_linking_flags_an_orphan_with_no_inbound_or_outbound_links(): void
    {
        $article = $this->makeArticle(['body' => '<p>No links in this body at all.</p>']);

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertSame(20, $card['categories']['internal_linking']['score']);
        $this->assertCount(2, $card['categories']['internal_linking']['issues']);
    }

    public function test_score_card_media_optimization_reflects_missing_featured_image(): void
    {
        $article = $this->makeArticle();

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertSame(50, $card['categories']['media_optimization']['score']);
        $this->assertSame(['No featured image set.'], $card['categories']['media_optimization']['issues']);
    }

    public function test_score_card_media_optimization_uses_dam_warnings_when_a_media_row_exists(): void
    {
        $article = $this->makeArticle(['image_path' => 'articles/guard.jpg']);
        Media::create([
            'original_name' => 'guard.jpg', 'disk' => 'public', 'disk_path' => 'articles/guard.jpg',
            'url' => '/storage/articles/guard.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg',
            'size' => 1000, 'alt_text' => 'A student passing the guard',
        ]);

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertSame(100, $card['categories']['media_optimization']['score']);
        $this->assertSame([], $card['categories']['media_optimization']['issues']);
    }

    // ============ body field (Phase R3) ============

    public function test_body_field_is_registered_with_edit_modes_only_and_no_generate_mode(): void
    {
        $definition = ActionRegistry::for('body');

        $this->assertSame(['Article', 'Page'], $definition['applicable_to']);
        $this->assertEqualsCanonicalizing(['improve', 'rewrite', 'expand', 'shorten', 'simplify'], $definition['modes']);
        $this->assertNotContains('generate', $definition['modes']);
        $this->assertSame('html', $definition['response_shape']);
    }

    public function test_generate_for_body_field_returns_clean_html_and_strips_code_fences(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText("```html\n<p>Improved paragraph.</p>\n```");

        $outcome = app(ContentAssistantService::class)->generate($article, 'body', 'improve');

        $this->assertSame('<p>Improved paragraph.</p>', $outcome['result']);
    }

    // ============ Quick Actions / Optimize Entire Article (Phase R3) ============

    public function test_quick_seo_only_queues_generations_for_seo_og_and_slug_fields_only(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->quickSeoOnly();

        $fields = AiGeneration::where('content_id', $article->id)->pluck('field')->sort()->values()->all();

        $this->assertSame(['meta_description', 'og_description', 'og_title', 'seo_title', 'slug'], $fields);
        Bus::assertDispatched(RunAiContentGeneration::class, 5);
    }

    public function test_quick_faq_only_queues_only_the_faq_field(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->quickFaqOnly();

        $this->assertSame(['faq'], AiGeneration::where('content_id', $article->id)->pluck('field')->all());
    }

    public function test_quick_body_action_queues_the_body_field_with_the_requested_mode(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->quickBodyAction('shorten');

        $generation = AiGeneration::where('content_id', $article->id)->sole();

        $this->assertSame('body', $generation->field);
        $this->assertSame('shorten', $generation->mode);
    }

    public function test_optimize_entire_article_queues_every_generate_mode_field_except_the_review_summary_and_body(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->optimizeEntireArticle();

        $fields = AiGeneration::where('content_id', $article->id)->pluck('field')->all();

        $this->assertNotContains('content_review_summary', $fields);
        $this->assertNotContains('body', $fields);
        $this->assertContains('seo_title', $fields);
        $this->assertContains('faq', $fields);
        $this->assertContains('internal_links', $fields);

        $expectedCount = collect(ActionRegistry::applicableTo('Article'))
            ->filter(fn (array $d, string $key) => $key !== 'content_review_summary' && in_array('generate', $d['modes'], true))
            ->count();

        $this->assertCount($expectedCount, $fields);
        Bus::assertDispatched(RunAiContentGeneration::class, $expectedCount);
    }

    public function test_optimize_entire_article_never_queues_the_body_field_for_pages_either(): void
    {
        Bus::fake();

        $page = $this->makePage();
        $this->makePanel($page)->optimizeEntireArticle();

        $this->assertNotContains('body', AiGeneration::where('content_id', $page->id)->pluck('field')->all());
    }

    public function test_generation_progress_reports_done_versus_total_within_the_recent_batch(): void
    {
        $article = $this->makeArticle();

        AiGeneration::create(['content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate', 'status' => 'completed']);
        AiGeneration::create(['content_type' => 'Article', 'content_id' => $article->id, 'field' => 'meta_description', 'mode' => 'generate', 'status' => 'queued']);
        AiGeneration::create(['content_type' => 'Article', 'content_id' => $article->id, 'field' => 'og_title', 'mode' => 'generate', 'status' => 'processing']);

        $this->assertSame('1 of 3 done', $this->makePanel($article)->generationProgress);
    }

    public function test_generation_progress_is_null_when_nothing_is_pending(): void
    {
        $article = $this->makeArticle();

        $this->assertNull($this->makePanel($article)->generationProgress);
    }
}
