<?php

namespace Tests\Feature;

use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Services\KnowledgeBase\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * KnowledgeBaseService::retrieveChunks()'s new semantic path — real vector search when an
 * embedding provider is configured and content is indexed, graceful fallback to the pre-existing
 * keyword/AI-ranking path otherwise (see the class's own docblock and CLAUDE.md's RAG section).
 * The pre-existing keyword-only behavior itself stays covered by KnowledgeBaseRetrievalTest.php —
 * this file is additive, not a replacement.
 */
class RagRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private function configureEmbeddings(): AiProviderConfig
    {
        $config = AiProviderConfig::where('slug', 'openai')->first();
        $config->forceFill(['api_key' => 'sk-test', 'is_enabled' => true, 'embedding_model' => 'text-embedding-3-small'])->save();
        AiProviderSetting::current()->forceFill(['embedding_provider_config_id' => $config->id])->save();

        return $config->fresh();
    }

    private function fakeEmbeddingResponse(array $vector): array
    {
        return ['data' => [['index' => 0, 'embedding' => $vector]]];
    }

    public function test_retrieve_chunks_uses_semantic_search_when_indexed(): void
    {
        $this->configureEmbeddings();

        // متن یکسان کافی است — با یک fake ثابت هر متنی (کوئری یا content) همان بردار را می‌گیرد،
        // پس شباهت کسینوسی همیشه ۱٫۰ است و صرفا رفتار «مسیر معنایی به‌جای fallback استفاده شد» را
        // تایید می‌کنیم، نه دقتِ رتبه‌بندی را
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbeddingResponse([1, 0, 0]))]);

        $entry = KnowledgeEntry::create([
            'title' => 'Guard Passing Basics', 'category' => 'Martial Arts', 'locale' => 'en',
            'content' => 'Guard passing is a fundamental BJJ skill.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('guard passing technique', 'en', 5);

        $this->assertCount(1, $chunks);
        $this->assertSame($entry->id, $chunks[0]['knowledge_entry_id']);
        $this->assertNotNull($chunks[0]['chunk_id']);
        $this->assertFalse($chunks[0]['pinned']);
        $this->assertEqualsWithDelta(1.0, $chunks[0]['score'], 0.0001);
    }

    public function test_retrieve_chunks_falls_back_to_keyword_when_nothing_indexed(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbeddingResponse([1, 0]))]);

        // create() این ورودی را خودکار ایندکس می‌کند (RAG6) — عمدا آن را حذف می‌کنیم تا حالت
        // «چیزی هنوز ایندکس نشده» را شبیه‌سازی کنیم (مثلا یک نصب قدیمی که هنوز rag:reindex اجرا
        // نشده)
        $entry = KnowledgeEntry::create([
            'title' => 'Kimura Lock', 'category' => 'Martial Arts', 'locale' => 'en',
            'content' => 'The kimura is a shoulder lock submission technique.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        KnowledgeChunk::query()->delete();

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('kimura submission', 'en', 5);

        $this->assertNotEmpty($chunks);
        $this->assertSame($entry->id, $chunks[0]['knowledge_entry_id']);
        $this->assertNull($chunks[0]['chunk_id']);
    }

    public function test_retrieve_chunks_falls_back_to_keyword_when_no_embedding_provider(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'Business Hours', 'category' => 'Business Information', 'locale' => 'en',
            'content' => 'We are open every day except Sunday from nine to seven.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('business hours', 'en', 5);

        $this->assertNotEmpty($chunks);
        $this->assertSame($entry->id, $chunks[0]['knowledge_entry_id']);
        $this->assertNull($chunks[0]['chunk_id']);
    }

    public function test_pinned_entries_always_included_as_a_single_representative_chunk(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbeddingResponse([1, 0]))]);

        $entry = KnowledgeEntry::create([
            'title' => 'Business Name', 'category' => 'Business Information', 'locale' => 'en',
            'content' => 'Train with Ehsan is a self-defense and BJJ academy in Istanbul.',
            'status' => KnowledgeEntry::STATUS_ACTIVE, 'is_pinned' => true,
        ]);

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('completely unrelated query about tax law', 'en', 3);

        $this->assertCount(1, $chunks);
        $this->assertSame($entry->id, $chunks[0]['knowledge_entry_id']);
        $this->assertTrue($chunks[0]['pinned']);
        $this->assertEqualsWithDelta(1.0, $chunks[0]['score'], 0.0001);
    }

    public function test_pinned_entry_contributes_only_one_chunk_even_with_many_indexed(): void
    {
        $this->configureEmbeddings();

        // چون این محتوا به بیش از یک chunk تقسیم می‌شود، fake باید به‌ازای هر متنِ ورودی یک بردار
        // برگرداند (نه فقط یک آیتم ثابت) — وگرنه OpenAiProvider::embed() به‌خاطر «یک بردار به‌ازای
        // هر متن نیست» throw می‌کند و ایندکس‌کردن بی‌صدا (توسط قلاب مدل) شکست می‌خورد
        Http::fake(function ($request) {
            $count = count($request->data()['input'] ?? [1]);

            return Http::response([
                'data' => array_map(fn ($i) => ['index' => $i, 'embedding' => array_fill(0, 6, 0.4)], range(0, $count - 1)),
            ]);
        });

        // متن بلند برای این‌که ChunkingService بیش از یک chunk بسازد
        $longContent = implode(' ', array_fill(0, 500, 'policy'));

        $entry = KnowledgeEntry::create([
            'title' => 'Long Pinned Policy', 'category' => 'Policies', 'locale' => 'en',
            'content' => $longContent, 'status' => KnowledgeEntry::STATUS_ACTIVE, 'is_pinned' => true,
        ]);

        $this->assertGreaterThan(1, $entry->allChunks()->count());

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('policy', 'en', 5);

        $this->assertCount(1, $chunks);
        $this->assertTrue($chunks[0]['pinned']);
    }

    public function test_retrieve_chunks_excludes_archived_and_expired_entries(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbeddingResponse([1, 0]))]);

        KnowledgeEntry::create([
            'title' => 'Archived Fact', 'category' => 'General', 'locale' => 'en',
            'content' => 'This fact is archived and should never be retrieved.',
            'status' => KnowledgeEntry::STATUS_ARCHIVED,
        ]);

        KnowledgeEntry::create([
            'title' => 'Expired Fact', 'category' => 'General', 'locale' => 'en',
            'content' => 'This fact expired yesterday.',
            'status' => KnowledgeEntry::STATUS_ACTIVE, 'expires_at' => now()->subDay(),
        ]);

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('fact', 'en', 5);

        $this->assertSame([], $chunks);
    }

    public function test_retrieve_chunks_scopes_to_locale(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbeddingResponse([1, 0]))]);

        KnowledgeEntry::create([
            'title' => 'Turkish Fact', 'category' => 'General', 'locale' => 'tr',
            'content' => 'Bu bir Türkçe bilgi girişidir.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $chunks = app(KnowledgeBaseService::class)->retrieveChunks('anything', 'en', 5);

        $this->assertSame([], $chunks);
    }

    public function test_retrieve_relevant_derives_entries_from_retrieve_chunks(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbeddingResponse([1, 0]))]);

        $entry = KnowledgeEntry::create([
            'title' => 'Guard Passing', 'category' => 'Martial Arts', 'locale' => 'en',
            'content' => 'Guard passing content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $entries = app(KnowledgeBaseService::class)->retrieveRelevant('guard passing', 'en', 5);

        $this->assertCount(1, $entries);
        $this->assertTrue($entries->first()->is($entry));
    }

    public function test_retrieve_relevant_returns_empty_collection_when_nothing_matches(): void
    {
        $entries = app(KnowledgeBaseService::class)->retrieveRelevant('nothing here', 'en', 5);

        $this->assertCount(0, $entries);
    }
}
