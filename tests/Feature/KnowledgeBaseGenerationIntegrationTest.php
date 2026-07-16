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

class KnowledgeBaseGenerationIntegrationTest extends TestCase
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

    public function test_generate_injects_relevant_pinned_knowledge_into_the_prompt_and_reports_which_entries_were_used(): void
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

        $this->assertSame([$entry->id], $outcome['knowledge_entry_ids']);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'Gym Location') && str_contains($body, 'Relevant Knowledge Base Facts');
        });
    }

    public function test_generate_reports_no_knowledge_entries_when_none_are_relevant_or_available(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('A great SEO title');

        $outcome = app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        $this->assertSame([], $outcome['knowledge_entry_ids']);
    }

    public function test_generate_never_pulls_knowledge_for_the_content_review_summary_field(): void
    {
        $article = $this->makeArticle();
        KnowledgeEntry::create([
            'title' => 'Gym Location', 'category' => 'Locations', 'locale' => 'en',
            'content' => 'Our main gym is in Kadikoy, Istanbul.', 'is_pinned' => true,
        ]);

        $this->fakeAnthropicText('A summary of the findings.');

        $outcome = app(ContentAssistantService::class)->generate($article, 'content_review_summary', 'generate');

        $this->assertSame([], $outcome['knowledge_entry_ids']);

        Http::assertSent(function ($request) {
            return ! str_contains($request->body(), 'Relevant Knowledge Base Facts');
        });
    }

    public function test_generate_never_pulls_knowledge_from_a_different_locale(): void
    {
        $article = $this->makeArticle(['locale' => 'en']);
        KnowledgeEntry::create([
            'title' => 'Turkish-only fact', 'category' => 'Business Information', 'locale' => 'tr',
            'content' => 'Sadece Türkçe içerik.', 'is_pinned' => true,
        ]);

        $this->fakeAnthropicText('A great SEO title');

        $outcome = app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        $this->assertSame([], $outcome['knowledge_entry_ids']);
    }

    public function test_run_ai_content_generation_persists_which_knowledge_entries_were_used_via_the_pivot(): void
    {
        $article = $this->makeArticle();
        $entry = KnowledgeEntry::create([
            'title' => 'Gym Location', 'category' => 'Locations', 'locale' => 'en',
            'content' => 'Our main gym is in Kadikoy, Istanbul.', 'is_pinned' => true,
        ]);

        $this->fakeAnthropicText('A great SEO title');

        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate', 'status' => 'queued',
        ]);

        (new RunAiContentGeneration($generation->id))->handle(app(ContentAssistantService::class));

        $this->assertTrue($generation->fresh()->knowledgeEntries->contains('id', $entry->id));
    }

    public function test_run_ai_content_generation_does_not_touch_the_pivot_when_no_knowledge_was_used(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('A great SEO title');

        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate', 'status' => 'queued',
        ]);

        (new RunAiContentGeneration($generation->id))->handle(app(ContentAssistantService::class));

        $this->assertCount(0, $generation->fresh()->knowledgeEntries);
    }
}
