<?php

namespace Tests\Feature;

use App\Jobs\IndexKnowledgeContent;
use App\Jobs\RebuildKnowledgeIndex;
use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Services\Rag\IndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagIndexingTest extends TestCase
{
    use RefreshDatabase;

    private function configureEmbeddings(): AiProviderConfig
    {
        $config = AiProviderConfig::where('slug', 'openai')->first();
        $config->forceFill(['api_key' => 'sk-test', 'is_enabled' => true, 'embedding_model' => 'text-embedding-3-small'])->save();
        AiProviderSetting::current()->forceFill(['embedding_provider_config_id' => $config->id])->save();

        return $config->fresh();
    }

    private function fakeEmbedding(int $dims = 6): array
    {
        return ['data' => [['index' => 0, 'embedding' => array_fill(0, $dims, 0.5)]]];
    }

    // --- IndexingService --------------------------------------------------------------------

    public function test_index_knowledge_entry_creates_chunks(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbedding())]);

        $entry = KnowledgeEntry::create([
            'title' => 'Guard Passing', 'category' => 'Martial Arts', 'locale' => 'en',
            'content' => 'Guard passing is a fundamental BJJ skill involving hip movement and pressure.',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        app(IndexingService::class)->indexKnowledgeEntry($entry);

        $this->assertSame(1, $entry->allChunks()->count());
        $chunk = $entry->allChunks()->first();
        $this->assertSame('KnowledgeEntry', $chunk->chunkable_type);
        $this->assertSame($entry->id, $chunk->chunkable_id);
        $this->assertCount(6, $chunk->embedding);
    }

    public function test_index_knowledge_entry_with_empty_content_stores_no_chunks(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'Empty', 'category' => 'General', 'locale' => 'en', 'content' => '',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        app(IndexingService::class)->indexKnowledgeEntry($entry);

        $this->assertSame(0, $entry->allChunks()->count());
    }

    public function test_reindexing_replaces_prior_chunks(): void
    {
        $this->configureEmbeddings();
        Http::fake([
            // یکی برای create() (اتصال booted() hook از RAG6 خودش صف می‌کند)، یکی برای update()
            'api.openai.com/*' => Http::sequence()
                ->push($this->fakeEmbedding(4))
                ->push($this->fakeEmbedding(4)),
        ]);

        $entry = KnowledgeEntry::create([
            'title' => 'Test', 'category' => 'General', 'locale' => 'en',
            'content' => 'First version of the content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        $this->assertSame(1, $entry->allChunks()->count());

        $entry->update(['content' => 'Second, updated version of the content.']);

        $this->assertSame(1, $entry->fresh()->allChunks()->count());
    }

    public function test_index_attachment_extracts_and_indexes(): void
    {
        $this->configureEmbeddings();

        $entry = KnowledgeEntry::create([
            'title' => 'Host', 'category' => 'General', 'locale' => 'en', 'content' => '',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $attachment = KnowledgeEntryAttachment::createFromUrl($entry, 'https://example.com/policy');

        Http::fake([
            'api.openai.com/*' => Http::response($this->fakeEmbedding()),
            'example.com/*' => Http::response('<html><body><p>Our policy text is here for testing extraction.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        app(IndexingService::class)->indexAttachment($attachment->fresh());

        $attachment->refresh();
        $this->assertSame(KnowledgeEntryAttachment::EXTRACTION_EXTRACTED, $attachment->extraction_status);
        $this->assertStringContainsString('policy text', $attachment->extracted_text);
        $this->assertSame(1, $attachment->chunks()->count());
        $this->assertSame('KnowledgeEntryAttachment', $attachment->chunks()->first()->chunkable_type);
    }

    public function test_index_attachment_records_extraction_failure_without_throwing(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'Host', 'category' => 'General', 'locale' => 'en', 'content' => '',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $attachment = KnowledgeEntryAttachment::createFromUrl($entry, 'https://example.com/missing');

        Http::fake(['example.com/*' => Http::response('', 500)]);

        app(IndexingService::class)->indexAttachment($attachment->fresh());

        $attachment->refresh();
        $this->assertSame(KnowledgeEntryAttachment::EXTRACTION_FAILED, $attachment->extraction_status);
        $this->assertNotNull($attachment->extraction_error);
        $this->assertSame(0, $attachment->chunks()->count());
    }

    // --- Lifecycle hooks ---------------------------------------------------------------------

    public function test_creating_an_entry_auto_indexes_it(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbedding())]);

        $entry = KnowledgeEntry::create([
            'title' => 'Auto Index', 'category' => 'General', 'locale' => 'en',
            'content' => 'This entry should be indexed automatically on create.',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $this->assertSame(1, $entry->allChunks()->count());
    }

    public function test_creating_an_entry_without_embedding_provider_does_not_throw(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'No Provider', 'category' => 'General', 'locale' => 'en',
            'content' => 'This should not throw even without an embedding provider configured.',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        $this->assertSame(0, $entry->allChunks()->count());
        $this->assertNotNull($entry->id);
    }

    public function test_updating_unrelated_field_does_not_reindex(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->fakeEmbedding())]);

        $entry = KnowledgeEntry::create([
            'title' => 'Unrelated Update', 'category' => 'General', 'locale' => 'en',
            'content' => 'Original content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        $this->assertSame(1, $entry->allChunks()->count());

        $entry->update(['priority' => KnowledgeEntry::PRIORITY_HIGH]);

        $this->assertSame(1, $entry->fresh()->allChunks()->count());
    }

    public function test_creating_attachment_auto_indexes_it(): void
    {
        $this->configureEmbeddings();

        $entry = KnowledgeEntry::create([
            'title' => 'Attachment Host', 'category' => 'General', 'locale' => 'en', 'content' => '',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response($this->fakeEmbedding()),
            'example.com/*' => Http::response('<html><body><p>Auto indexed attachment text.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $attachment = KnowledgeEntryAttachment::createFromUrl($entry, 'https://example.com/page');

        $attachment->refresh();
        $this->assertSame(KnowledgeEntryAttachment::EXTRACTION_EXTRACTED, $attachment->extraction_status);
        $this->assertSame(1, $attachment->chunks()->count());
    }

    public function test_deleting_entry_cascades_chunk_deletion(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbedding())]);

        $entry = KnowledgeEntry::create([
            'title' => 'Delete Me', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content that gets indexed then the entry is deleted.',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        $this->assertSame(1, $entry->allChunks()->count());

        $entryId = $entry->id;
        $entry->delete();

        $this->assertDatabaseMissing('knowledge_chunks', ['knowledge_entry_id' => $entryId]);
    }

    // --- Jobs ---------------------------------------------------------------------------------

    public function test_index_knowledge_content_job_swallows_embedding_failure(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'Job Test', 'category' => 'General', 'locale' => 'en',
            'content' => 'Some content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        // بدون configureEmbeddings() — ProviderManager::embed() throw می‌کند، اما جاب هرگز نباید
        // آن را دوباره throw کند (نگاه کنید به IndexKnowledgeContent::handle())
        (new IndexKnowledgeContent($entry))->handle(app(IndexingService::class));

        $this->assertSame(0, $entry->allChunks()->count());
    }

    public function test_rebuild_knowledge_index_job_reindexes_everything(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbedding())]);

        $entryA = KnowledgeEntry::create([
            'title' => 'A', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content A.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        $entryB = KnowledgeEntry::create([
            'title' => 'B', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content B.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        // پاک‌کردن دستیِ chunkهای ایجادشده توسط hook خودکار، تا مطمئن شویم این جاب واقعاً همه‌چیز
        // را از نو می‌سازد، نه فقط چیزی که از قبل بوده را دست‌نخورده می‌گذارد
        KnowledgeChunk::query()->delete();
        $this->assertSame(0, $entryA->allChunks()->count());

        (new RebuildKnowledgeIndex)->handle(app(IndexingService::class));

        $this->assertSame(1, $entryA->fresh()->allChunks()->count());
        $this->assertSame(1, $entryB->fresh()->allChunks()->count());
    }

    // --- rag:reindex command --------------------------------------------------------------------

    public function test_rag_reindex_command_reindexes_all_entries(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbedding())]);

        $entry = KnowledgeEntry::create([
            'title' => 'CLI Test', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content for the CLI reindex command.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        KnowledgeChunk::query()->delete();

        $this->artisan('rag:reindex')->assertSuccessful();

        $this->assertSame(1, $entry->fresh()->allChunks()->count());
    }

    public function test_rag_reindex_command_scoped_to_one_entry(): void
    {
        $this->configureEmbeddings();
        Http::fake(['api.openai.com/*' => Http::response($this->fakeEmbedding())]);

        $entryA = KnowledgeEntry::create([
            'title' => 'A', 'category' => 'General', 'locale' => 'en', 'content' => 'A content.',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        $entryB = KnowledgeEntry::create([
            'title' => 'B', 'category' => 'General', 'locale' => 'en', 'content' => 'B content.',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        KnowledgeChunk::query()->delete();

        $this->artisan('rag:reindex', ['--entry' => $entryA->id])->assertSuccessful();

        $this->assertSame(1, $entryA->fresh()->allChunks()->count());
        $this->assertSame(0, $entryB->fresh()->allChunks()->count());
    }

    public function test_rag_reindex_command_reports_error_for_unknown_entry(): void
    {
        $this->artisan('rag:reindex', ['--entry' => 999999])->assertFailed();
    }
}
