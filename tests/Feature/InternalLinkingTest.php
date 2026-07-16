<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Page;
use App\Services\InternalLinking\LinkGraphService;
use App\Services\InternalLinking\SuggestionEngine;
use App\Services\Seo\InternalLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function graphService(): LinkGraphService
    {
        return app(LinkGraphService::class);
    }

    private function suggestionEngine(): SuggestionEngine
    {
        return app(SuggestionEngine::class);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en', 'title' => 'Article', 'slug' => 'article-'.uniqid(),
            'body' => '<p>x</p>', 'author_name' => 'Ehsan', 'status' => 'published',
        ], $overrides));
    }

    // ============ InternalLinkResolver ============

    public function test_resolver_parses_internal_paths_without_a_database_query(): void
    {
        $resolver = app(InternalLinkResolver::class);

        $this->assertSame(['type' => 'Article', 'locale' => 'en', 'slug' => 'foo'], $resolver->parseInternalPath('/blog/foo'));
        $this->assertSame(['type' => 'Article', 'locale' => 'tr', 'slug' => 'foo'], $resolver->parseInternalPath('/tr/blog/foo'));
        $this->assertSame(['type' => 'Page', 'locale' => 'en', 'slug' => 'privacy'], $resolver->parseInternalPath('/privacy'));
        $this->assertNull($resolver->parseInternalPath('/blog')); // مسیر ایستا، نه یک محتوای پویا
        $this->assertNull($resolver->parseInternalPath('https://example.com/x'));
    }

    // ============ LinkGraphService ============

    public function test_graph_computes_inbound_and_outbound_counts(): void
    {
        $hub = $this->makeArticle(['slug' => 'hub', 'body' => '<p><a href="/blog/target">see this</a></p>']);
        $target = $this->makeArticle(['slug' => 'target']);
        $orphan = $this->makeArticle(['slug' => 'orphan']);

        $nodes = $this->graphService()->build()['nodes'];

        $this->assertSame(0, $nodes['Article:'.$hub->id]['inbound']);
        $this->assertSame(1, $nodes['Article:'.$hub->id]['outbound']);
        $this->assertSame(1, $nodes['Article:'.$target->id]['inbound']);
        $this->assertSame(0, $nodes['Article:'.$target->id]['outbound']);
        $this->assertSame(0, $nodes['Article:'.$orphan->id]['inbound']);
    }

    public function test_graph_ignores_external_links_and_does_not_double_count_repeated_links(): void
    {
        $target = $this->makeArticle(['slug' => 'target']);
        $source = $this->makeArticle([
            'slug' => 'source',
            'body' => '<p><a href="/blog/target">one</a> <a href="/blog/target">two</a> <a href="https://example.com">external</a></p>',
        ]);

        $nodes = $this->graphService()->build()['nodes'];

        $this->assertSame(1, $nodes['Article:'.$source->id]['outbound']);
        $this->assertSame(1, $nodes['Article:'.$target->id]['inbound']);
    }

    public function test_no_inbound_and_no_outbound_findings_include_drafts_unlike_seo_centers_orphan_check(): void
    {
        $draft = $this->makeArticle(['slug' => 'draft-item', 'title' => 'Draft Item', 'status' => 'draft']);

        $nodes = $this->graphService()->build()['nodes'];
        $noInbound = collect($this->graphService()->noInboundLinks($nodes));
        $noOutbound = collect($this->graphService()->noOutboundLinks($nodes));

        // SeoAuditService::orphanPages() عمدا draft را نادیده می‌گیرد؛ این چک باید همان draft را هم ببیند
        $this->assertTrue($noInbound->contains(fn ($f) => str_contains($f['title'], 'Draft Item')));
        $this->assertTrue($noOutbound->contains(fn ($f) => str_contains($f['title'], 'Draft Item')));
    }

    public function test_weak_internal_linking_requires_at_least_one_inbound_link_but_below_threshold(): void
    {
        $orphan = $this->makeArticle(['slug' => 'orphan', 'title' => 'Orphan Article']);
        $weak = $this->makeArticle(['slug' => 'weak', 'title' => 'Weak Article']);
        $this->makeArticle(['slug' => 'linker', 'title' => 'Linker Article', 'body' => '<p><a href="/blog/weak">x</a></p>']);

        $nodes = $this->graphService()->build()['nodes'];
        $weakFindings = collect($this->graphService()->weakInternalLinking($nodes));

        $this->assertTrue($weakFindings->contains(fn ($f) => str_contains($f['title'], 'Weak Article')));
        $this->assertFalse($weakFindings->contains(fn ($f) => str_contains($f['title'], 'Orphan Article')));
    }

    public function test_redirect_chains_is_always_empty_no_redirect_system_exists(): void
    {
        $this->assertSame([], $this->graphService()->redirectChains());
    }

    // ============ SuggestionEngine ============

    public function test_suggestions_favor_keyword_and_category_matches_over_unrelated_content(): void
    {
        $target = $this->makeArticle(['slug' => 'bjj-guide', 'title' => 'BJJ Guide', 'category' => 'BJJ']);
        $target->keywords()->create(['keyword' => 'bjj training']);

        $relatedSource = $this->makeArticle([
            'slug' => 'related', 'title' => 'Martial Arts Basics', 'category' => 'BJJ',
            'body' => '<p>Everything about bjj training for newcomers.</p>',
        ]);

        $unrelatedSource = $this->makeArticle([
            'slug' => 'unrelated', 'title' => 'Diet Tips', 'category' => 'Nutrition',
            'body' => '<p>Totally different subject matter here.</p>',
        ]);

        $suggestions = $this->suggestionEngine()->suggest();
        $sourceIds = $suggestions->pluck('source.id')->all();

        $this->assertContains($relatedSource->id, $sourceIds);
        $this->assertNotContains($unrelatedSource->id, $sourceIds);
    }

    public function test_suggestions_never_cross_locale(): void
    {
        $enTarget = $this->makeArticle(['slug' => 'en-target', 'locale' => 'en', 'category' => 'BJJ']);
        $enTarget->keywords()->create(['keyword' => 'shared keyword']);

        $trCandidate = $this->makeArticle([
            'slug' => 'tr-candidate', 'locale' => 'tr', 'category' => 'BJJ',
            'body' => '<p>shared keyword mentioned here too</p>',
        ]);

        $suggestions = $this->suggestionEngine()->suggest();

        $this->assertFalse($suggestions->contains(fn ($s) => $s['source']['id'] === $trCandidate->id));
    }

    public function test_suggestions_exclude_pairs_that_already_link(): void
    {
        $target = $this->makeArticle(['slug' => 'already-linked-target', 'category' => 'BJJ']);
        $target->keywords()->create(['keyword' => 'unique keyword phrase']);

        $source = $this->makeArticle([
            'slug' => 'already-linking-source', 'category' => 'BJJ',
            'body' => '<p>mentions unique keyword phrase and <a href="/blog/already-linked-target">already links</a></p>',
        ]);

        $suggestions = $this->suggestionEngine()->suggest();

        $this->assertFalse($suggestions->contains(
            fn ($s) => $s['source']['id'] === $source->id && $s['target']['id'] === $target->id
        ));
    }

    public function test_generate_and_persist_preserves_approved_and_dismissed_decisions(): void
    {
        $target = $this->makeArticle(['slug' => 'persist-target', 'category' => 'BJJ']);
        $target->keywords()->create(['keyword' => 'persist keyword']);
        $this->makeArticle(['slug' => 'persist-source', 'category' => 'BJJ', 'body' => '<p>persist keyword here</p>']);

        $this->suggestionEngine()->generateAndPersist();
        $suggestion = InternalLinkSuggestion::first();
        $this->assertNotNull($suggestion);

        $suggestion->update(['status' => 'approved', 'approved_at' => now()]);

        $this->suggestionEngine()->generateAndPersist();

        $this->assertSame('approved', $suggestion->fresh()->status);
        $this->assertDatabaseHas('internal_link_suggestions', ['id' => $suggestion->id, 'status' => 'approved']);
    }

    public function test_generate_and_persist_removes_stale_pending_suggestions(): void
    {
        $target = $this->makeArticle(['slug' => 'stale-target', 'category' => 'BJJ']);
        $target->keywords()->create(['keyword' => 'stale keyword']);
        $source = $this->makeArticle(['slug' => 'stale-source', 'category' => 'BJJ', 'body' => '<p>stale keyword here</p>']);

        $this->suggestionEngine()->generateAndPersist();
        $this->assertDatabaseHas('internal_link_suggestions', [
            'source_type' => 'Article', 'source_id' => $source->id,
            'target_type' => 'Article', 'target_id' => $target->id,
        ]);

        // محتوا عوض می‌شود، دیگر کلیدواژه مشترکی نیست — پیشنهاد باید ناپدید شود
        $source->update(['body' => '<p>completely different content now</p>']);
        $source->category = 'Nutrition';
        $source->save();

        $this->suggestionEngine()->generateAndPersist();

        $this->assertDatabaseMissing('internal_link_suggestions', [
            'source_type' => 'Article', 'source_id' => $source->id,
            'target_type' => 'Article', 'target_id' => $target->id,
        ]);
    }

    // ============ Keyword relation ============

    public function test_article_and_page_can_have_multiple_keywords(): void
    {
        $article = $this->makeArticle();
        $article->keywords()->create(['keyword' => 'first']);
        $article->keywords()->create(['keyword' => 'second']);

        $page = Page::create(['locale' => 'en', 'title' => 'Page', 'slug' => 'page-'.uniqid(), 'body' => '<p>x</p>', 'status' => 'published']);
        $page->keywords()->create(['keyword' => 'page keyword']);

        $this->assertCount(2, $article->keywords);
        $this->assertCount(1, $page->keywords);
        $this->assertSame('page keyword', $page->keywords->first()->keyword);
    }
}
