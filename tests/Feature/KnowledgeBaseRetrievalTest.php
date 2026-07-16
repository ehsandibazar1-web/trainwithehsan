<?php

namespace Tests\Feature;

use App\Models\KnowledgeEntry;
use App\Models\Tag;
use App\Services\KnowledgeBase\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeBaseRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(array $overrides = []): KnowledgeEntry
    {
        return KnowledgeEntry::create(array_merge([
            'title' => 'Generic entry',
            'category' => 'Business Information',
            'locale' => 'en',
            'content' => 'Some generic reference content.',
        ], $overrides));
    }

    private function fakeAnthropicJson(string $json): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $json]],
            ], 200),
        ]);
    }

    public function test_pinned_entries_are_always_included(): void
    {
        $pinned = $this->makeEntry(['title' => 'Pinned entry', 'is_pinned' => true, 'content' => 'Totally unrelated to the query.']);

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('BJJ classes for kids', 'en');

        $this->assertTrue($results->contains('id', $pinned->id));
    }

    public function test_expired_and_inactive_entries_are_never_retrieved(): void
    {
        $expired = $this->makeEntry(['title' => 'Expired', 'expires_at' => now()->subDay(), 'is_pinned' => true]);
        $draft = $this->makeEntry(['title' => 'Draft', 'status' => KnowledgeEntry::STATUS_DRAFT, 'is_pinned' => true]);
        $archived = $this->makeEntry(['title' => 'Archived', 'status' => KnowledgeEntry::STATUS_ARCHIVED, 'is_pinned' => true]);

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('anything', 'en');

        $this->assertFalse($results->contains('id', $expired->id));
        $this->assertFalse($results->contains('id', $draft->id));
        $this->assertFalse($results->contains('id', $archived->id));
    }

    public function test_locale_scoping_excludes_entries_from_other_languages(): void
    {
        $tr = $this->makeEntry(['title' => 'Turkish entry', 'locale' => 'tr', 'is_pinned' => true]);

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('anything', 'en');

        $this->assertFalse($results->contains('id', $tr->id));
    }

    public function test_keyword_fallback_ranks_relevant_entries_higher_when_no_ai_provider_is_configured(): void
    {
        config(['services.anthropic.key' => null]);

        $relevant = $this->makeEntry(['title' => 'Kids BJJ Program', 'content' => 'Our kids Brazilian Jiu-Jitsu program teaches discipline and self-defense.']);
        $unrelated = $this->makeEntry(['title' => 'Refund Policy', 'content' => 'Membership refunds are processed within thirty days.']);

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('Write an article about our kids Brazilian Jiu-Jitsu program', 'en', limit: 1);

        $this->assertTrue($results->contains('id', $relevant->id));
        $this->assertFalse($results->contains('id', $unrelated->id));
    }

    public function test_ai_ranking_is_used_when_a_provider_is_configured(): void
    {
        $chosen = $this->makeEntry(['title' => 'Istanbul Location', 'content' => 'Our main gym is in Kadikoy, Istanbul.']);
        $other = $this->makeEntry(['title' => 'Founder Biography', 'content' => 'Ehsan has trained for fifteen years.']);

        $this->fakeAnthropicJson(json_encode([$chosen->id]));

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('Where is the gym located?', 'en', limit: 5);

        $this->assertTrue($results->contains('id', $chosen->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    public function test_ai_explicitly_returning_no_relevant_entries_is_honored_not_treated_as_a_fallback(): void
    {
        $this->makeEntry(['title' => 'Unrelated entry']);
        $this->fakeAnthropicJson('[]');

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('something specific', 'en');

        $this->assertCount(0, $results);
    }

    public function test_a_failed_ai_call_falls_back_to_keyword_ranking_instead_of_returning_nothing(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response('server error', 500)]);

        $relevant = $this->makeEntry(['title' => 'Kids BJJ Program', 'content' => 'Our kids Brazilian Jiu-Jitsu program teaches discipline.']);

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('Write about our kids Brazilian Jiu-Jitsu program', 'en');

        $this->assertTrue($results->contains('id', $relevant->id));
    }

    public function test_limit_is_respected_across_pinned_and_ranked_entries(): void
    {
        Tag::query();
        for ($i = 0; $i < 3; $i++) {
            $this->makeEntry(['title' => "Pinned {$i}", 'is_pinned' => true]);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->makeEntry(['title' => "Regular {$i}"]);
        }

        $results = app(KnowledgeBaseService::class)->retrieveRelevant('anything', 'en', limit: 4);

        $this->assertCount(4, $results);
    }
}
