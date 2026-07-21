<?php

namespace Tests\Feature;

use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEntry;
use App\Services\AiAssistant\ProviderManager;
use App\Services\Rag\ChunkingService;
use App\Services\Rag\Contracts\VectorStore;
use App\Services\Rag\TextExtractionService;
use App\Services\Rag\VectorStores\EloquentCosineVectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagPipelineTest extends TestCase
{
    use RefreshDatabase;

    // --- TextExtractionService ---------------------------------------------------------------

    public function test_extracts_text_from_html_stripping_scripts_and_keeping_block_text(): void
    {
        $html = '<html><body><h1>Title</h1><p>First paragraph.</p><ul><li>Item one</li><li>Item two</li></ul><script>var x=1;</script></body></html>';

        $text = (new TextExtractionService)->extractFromHtml($html);

        $this->assertStringContainsString('Title', $text);
        $this->assertStringContainsString('First paragraph.', $text);
        $this->assertStringContainsString('Item one', $text);
        $this->assertStringNotContainsString('var x=1', $text);
    }

    public function test_extracts_text_from_html_fragment_with_no_block_tags(): void
    {
        $text = (new TextExtractionService)->extractFromHtml('<span>just some inline text</span>');

        $this->assertStringContainsString('just some inline text', $text);
    }

    public function test_extracts_text_from_markdown_stripping_syntax(): void
    {
        $md = "# Heading\n\nSome **bold** and _italic_ text with a [link](https://example.com).\n\n- item one\n- item two";

        $text = (new TextExtractionService)->extractFromMarkdown($md);

        $this->assertStringContainsString('Heading', $text);
        $this->assertStringContainsString('bold', $text);
        $this->assertStringContainsString('italic', $text);
        $this->assertStringContainsString('link', $text);
        $this->assertStringNotContainsString('**', $text);
        $this->assertStringNotContainsString('[link]', $text);
        $this->assertStringNotContainsString('#', $text);
    }

    public function test_extracts_text_from_plain_text_normalizing_line_endings(): void
    {
        $text = (new TextExtractionService)->extractFromText("line one\r\nline two\r\n");

        $this->assertSame("line one\nline two", $text);
    }

    public function test_extracts_text_from_docx(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_').'.docx';

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Hello</w:t></w:r><w:r><w:t xml:space="preserve"> World</w:t></w:r></w:p>
    <w:p><w:r><w:t>Second paragraph.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $text = (new TextExtractionService)->extractFromDocx($tmp);

        @unlink($tmp);

        $this->assertSame("Hello World\n\nSecond paragraph.", $text);
    }

    public function test_extracts_text_from_pdf(): void
    {
        // fixtureی داخلِ خودِ ریپو — پوشه‌ی samples در نصبِ --prefer-dist (مثل CI) اصلاً وجود ندارد
        // (export-ignore)، پس اتکا به فایلِ نمونه‌ی vendor فقط روی نصب‌های from-source پاس می‌شد
        $text = (new TextExtractionService)->extractFromPdf(base_path('tests/Fixtures/Rag/sample.pdf'));

        $this->assertStringContainsString('Lorem ipsum', $text);
    }

    public function test_extract_from_pdf_throws_on_corrupted_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bad_').'.pdf';
        file_put_contents($tmp, 'not a real pdf');

        $this->expectException(\RuntimeException::class);

        try {
            (new TextExtractionService)->extractFromPdf($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_extract_from_url_fetches_and_extracts_html(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><body><p>Fetched page content.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $text = (new TextExtractionService)->extractFromUrl('https://example.com/page');

        $this->assertStringContainsString('Fetched page content.', $text);
    }

    public function test_extract_from_url_throws_on_http_failure(): void
    {
        Http::fake(['example.com/*' => Http::response('', 500)]);

        $this->expectException(\RuntimeException::class);

        (new TextExtractionService)->extractFromUrl('https://example.com/missing');
    }

    // --- ChunkingService -----------------------------------------------------------------------

    public function test_chunking_returns_single_chunk_for_short_text(): void
    {
        $text = implode(' ', array_fill(0, 50, 'word'));

        $chunks = (new ChunkingService)->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertSame($text, $chunks[0]);
    }

    public function test_chunking_splits_long_text_with_overlap(): void
    {
        $words = [];
        for ($i = 1; $i <= 500; $i++) {
            $words[] = "w{$i}";
        }
        $text = implode(' ', $words);

        $chunks = (new ChunkingService)->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $wordCount = count(preg_split('/\s+/', trim($chunk)));
            $this->assertLessThanOrEqual(220, $wordCount);
            $this->assertGreaterThanOrEqual(20, $wordCount);
        }

        $firstChunkWords = explode(' ', $chunks[0]);
        $secondChunkWords = explode(' ', $chunks[1]);
        $this->assertContains(end($firstChunkWords), array_slice($secondChunkWords, 0, 45));
    }

    public function test_chunking_returns_empty_array_for_blank_text(): void
    {
        $this->assertSame([], (new ChunkingService)->chunk('   '));
        $this->assertSame([], (new ChunkingService)->chunk(''));
    }

    // --- VectorStore (EloquentCosineVectorStore) -----------------------------------------------

    private function makeKnowledgeEntry(): KnowledgeEntry
    {
        // content خالی عمداً است — قلاب booted() این پروژه هر KnowledgeEntry تازه را خودکار صف
        // می‌کند (RAG6)، و با content خالی ChunkingService چیزی برنمی‌گرداند، پس هیچ embed واقعی‌ای
        // فراخوانی نمی‌شود و chunkهای دستیِ خودِ این تست دست‌نخورده می‌مانند.
        return KnowledgeEntry::create([
            'title' => 'Test Entry',
            'category' => 'General',
            'locale' => 'en',
            'content' => '',
            'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
    }

    public function test_vector_store_upsert_and_search_returns_scored_results(): void
    {
        $entry = $this->makeKnowledgeEntry();
        $store = app(VectorStore::class);
        $this->assertInstanceOf(EloquentCosineVectorStore::class, $store);

        $store->upsert('KnowledgeEntry', $entry->id, $entry->id, [
            ['chunk_index' => 0, 'text' => 'chunk a', 'char_count' => 7, 'embedding' => [1, 0, 0], 'embedding_model' => 'test', 'embedding_dims' => 3, 'locale' => 'en'],
            ['chunk_index' => 1, 'text' => 'chunk b', 'char_count' => 7, 'embedding' => [0, 1, 0], 'embedding_model' => 'test', 'embedding_dims' => 3, 'locale' => 'en'],
        ]);

        $results = $store->search([1, 0, 0], 5);

        $this->assertCount(2, $results);
        $this->assertSame('chunk a', $results[0]['text']);
        $this->assertEqualsWithDelta(1.0, $results[0]['score'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $results[1]['score'], 0.0001);
    }

    public function test_vector_store_upsert_replaces_prior_chunks_for_same_owner(): void
    {
        $entry = $this->makeKnowledgeEntry();
        $store = app(VectorStore::class);

        $store->upsert('KnowledgeEntry', $entry->id, $entry->id, [
            ['chunk_index' => 0, 'text' => 'old', 'char_count' => 3, 'embedding' => [1, 0], 'embedding_model' => 'test', 'embedding_dims' => 2, 'locale' => 'en'],
            ['chunk_index' => 1, 'text' => 'old2', 'char_count' => 4, 'embedding' => [1, 0], 'embedding_model' => 'test', 'embedding_dims' => 2, 'locale' => 'en'],
        ]);
        $this->assertSame(2, KnowledgeChunk::count());

        $store->upsert('KnowledgeEntry', $entry->id, $entry->id, [
            ['chunk_index' => 0, 'text' => 'new', 'char_count' => 3, 'embedding' => [1, 0], 'embedding_model' => 'test', 'embedding_dims' => 2, 'locale' => 'en'],
        ]);

        $this->assertSame(1, KnowledgeChunk::count());
        $this->assertSame('new', KnowledgeChunk::first()->text);
    }

    public function test_vector_store_delete_for_owner_removes_only_that_owners_chunks(): void
    {
        $entryA = $this->makeKnowledgeEntry();
        $entryB = $this->makeKnowledgeEntry();
        $store = app(VectorStore::class);

        $store->upsert('KnowledgeEntry', $entryA->id, $entryA->id, [
            ['chunk_index' => 0, 'text' => 'a', 'char_count' => 1, 'embedding' => [1], 'embedding_model' => 'test', 'embedding_dims' => 1, 'locale' => 'en'],
        ]);
        $store->upsert('KnowledgeEntry', $entryB->id, $entryB->id, [
            ['chunk_index' => 0, 'text' => 'b', 'char_count' => 1, 'embedding' => [1], 'embedding_model' => 'test', 'embedding_dims' => 1, 'locale' => 'en'],
        ]);

        $store->deleteForOwner('KnowledgeEntry', $entryA->id);

        $this->assertSame(1, KnowledgeChunk::count());
        $this->assertSame($entryB->id, KnowledgeChunk::first()->chunkable_id);
    }

    public function test_vector_store_search_respects_knowledge_entry_id_filter(): void
    {
        $entryA = $this->makeKnowledgeEntry();
        $entryB = $this->makeKnowledgeEntry();
        $store = app(VectorStore::class);

        $store->upsert('KnowledgeEntry', $entryA->id, $entryA->id, [
            ['chunk_index' => 0, 'text' => 'a', 'char_count' => 1, 'embedding' => [1, 0], 'embedding_model' => 'test', 'embedding_dims' => 2, 'locale' => 'en'],
        ]);
        $store->upsert('KnowledgeEntry', $entryB->id, $entryB->id, [
            ['chunk_index' => 0, 'text' => 'b', 'char_count' => 1, 'embedding' => [1, 0], 'embedding_model' => 'test', 'embedding_dims' => 2, 'locale' => 'en'],
        ]);

        $results = $store->search([1, 0], 5, ['knowledge_entry_id_in' => [$entryB->id]]);

        $this->assertCount(1, $results);
        $this->assertSame($entryB->id, $results[0]['knowledge_entry_id']);
    }

    public function test_vector_store_count(): void
    {
        $entry = $this->makeKnowledgeEntry();
        $store = app(VectorStore::class);
        $this->assertSame(0, $store->count());

        $store->upsert('KnowledgeEntry', $entry->id, $entry->id, [
            ['chunk_index' => 0, 'text' => 'a', 'char_count' => 1, 'embedding' => [1], 'embedding_model' => 'test', 'embedding_dims' => 1, 'locale' => 'en'],
        ]);

        $this->assertSame(1, $store->count());
    }

    // --- ProviderManager::embed() ---------------------------------------------------------------

    private function configureEmbeddingProvider(string $slug = 'openai'): AiProviderConfig
    {
        $config = AiProviderConfig::where('slug', $slug)->first();
        $config->forceFill(['api_key' => 'sk-test', 'is_enabled' => true, 'embedding_model' => 'test-embedding-model'])->save();
        AiProviderSetting::current()->forceFill(['embedding_provider_config_id' => $config->id])->save();

        return $config->fresh();
    }

    public function test_embed_throws_when_no_provider_configured(): void
    {
        $this->expectException(\RuntimeException::class);

        app(ProviderManager::class)->embed(['hello']);
    }

    public function test_embed_calls_openai_and_returns_vectors(): void
    {
        $this->configureEmbeddingProvider('openai');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.1, 0.2]],
                    ['index' => 1, 'embedding' => [0.3, 0.4]],
                ],
                'usage' => ['prompt_tokens' => 10],
            ]),
        ]);

        $vectors = app(ProviderManager::class)->embed(['a', 'b']);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $vectors);
        $this->assertDatabaseHas('ai_usage_logs', ['provider_slug' => 'openai', 'action_key' => 'embedding', 'status' => 'success']);
    }

    public function test_embed_calls_gemini_and_returns_vectors(): void
    {
        $this->configureEmbeddingProvider('gemini');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embeddings' => [
                    ['values' => [0.5, 0.6]],
                ],
            ]),
        ]);

        $vectors = app(ProviderManager::class)->embed(['hello']);

        $this->assertSame([[0.5, 0.6]], $vectors);
    }

    public function test_embed_logs_failure_and_rethrows(): void
    {
        $this->configureEmbeddingProvider('openai');

        Http::fake(['api.openai.com/*' => Http::response('', 500)]);

        try {
            app(ProviderManager::class)->embed(['x']);
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('ai_usage_logs', ['provider_slug' => 'openai', 'action_key' => 'embedding', 'status' => 'failed']);
    }

    public function test_grok_and_deepseek_are_not_embedding_capable(): void
    {
        $grok = AiProviderConfig::where('slug', 'grok')->first();
        $grok->forceFill(['api_key' => 'x', 'is_enabled' => true, 'embedding_model' => 'whatever'])->save();
        AiProviderSetting::current()->forceFill(['embedding_provider_config_id' => $grok->id])->save();

        $this->expectException(\RuntimeException::class);
        app(ProviderManager::class)->embed(['x']);
    }
}
