<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\AiAssistant\DiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
