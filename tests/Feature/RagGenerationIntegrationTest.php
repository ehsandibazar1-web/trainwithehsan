<?php

namespace Tests\Feature;

use App\Jobs\RunAiContentGeneration;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\KnowledgeEntry;
use App\Services\AiAssistant\ContentAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ContentAssistantService::generate() now injects only retrieved CHUNK text into the prompt (not
 * whole KnowledgeEntry content) and returns retrieved_chunks alongside knowledge_entry_ids — see
 * CLAUDE.md's RAG section. This must never send the entire Knowledge Base to the AI, and a
 * generation with no retrieved chunks must stay byte-identical to before this feature (same
 * invariant KnowledgeBaseGenerationIntegrationTest.php already covers for the older, entry-level
 * shape — this file only tests what RAG changed).
 */
class RagGenerationIntegrationTest extends TestCase
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
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $text]]], 200),
        ]);
    }

    public function test_generate_injects_only_chunk_text_and_returns_retrieved_chunks(): void
    {
        $article = $this->makeArticle();

        $entry = KnowledgeEntry::create([
            'title' => 'Gym Location',
            'category' => 'Locations',
            'locale' => 'en',
            'content' => 'Our main gym is in Kadikoy, Istanbul.',
            'is_pinned' => true,
        ]);

        $this->fakeAnthropicText('A great SEO title');

        $outcome = app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        $this->assertArrayHasKey('retrieved_chunks', $outcome);
        $this->assertNotEmpty($outcome['retrieved_chunks']);
        $this->assertSame($entry->id, $outcome['retrieved_chunks'][0]['knowledge_entry_id']);
        $this->assertSame('Gym Location', $outcome['retrieved_chunks'][0]['source']);
        $this->assertSame([$entry->id], $outcome['knowledge_entry_ids']);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'Our main gym is in Kadikoy')
                && str_contains($body, 'Relevant Knowledge Base Facts');
        });
    }

    public function test_generate_returns_empty_retrieved_chunks_when_knowledge_base_is_empty(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('A great SEO title');

        $outcome = app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        $this->assertSame([], $outcome['retrieved_chunks']);
        $this->assertSame([], $outcome['knowledge_entry_ids']);
    }

    public function test_content_review_summary_never_pulls_knowledge_chunks(): void
    {
        KnowledgeEntry::create([
            'title' => 'Pinned Fact',
            'category' => 'General',
            'locale' => 'en',
            'content' => 'Should never appear for content_review_summary.',
            'is_pinned' => true,
        ]);

        $article = $this->makeArticle();
        $this->fakeAnthropicText('Summary');

        $outcome = app(ContentAssistantService::class)->generate($article, 'content_review_summary', 'generate');

        $this->assertSame([], $outcome['retrieved_chunks']);
    }

    public function test_run_ai_content_generation_job_persists_retrieved_chunks(): void
    {
        $article = $this->makeArticle();

        $entry = KnowledgeEntry::create([
            'title' => 'Pinned Fact',
            'category' => 'General',
            'locale' => 'en',
            'content' => 'A pinned fact used by every generation.',
            'is_pinned' => true,
        ]);

        $this->fakeAnthropicText('Generated title');

        $generation = AiGeneration::create([
            'content_type' => $article->getMorphClass(),
            'content_id' => $article->id,
            'field' => 'seo_title',
            'mode' => 'generate',
            'status' => 'queued',
        ]);

        (new RunAiContentGeneration($generation->id))->handle(app(ContentAssistantService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertNotEmpty($generation->retrieved_chunks);
        $this->assertSame($entry->id, $generation->retrieved_chunks[0]['knowledge_entry_id']);
        $this->assertTrue($generation->knowledgeEntries->contains($entry));
    }

    public function test_run_ai_content_generation_job_persists_empty_retrieved_chunks_when_none_found(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('Generated title');

        $generation = AiGeneration::create([
            'content_type' => $article->getMorphClass(),
            'content_id' => $article->id,
            'field' => 'seo_title',
            'mode' => 'generate',
            'status' => 'queued',
        ]);

        (new RunAiContentGeneration($generation->id))->handle(app(ContentAssistantService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertSame([], $generation->retrieved_chunks);
    }
}
