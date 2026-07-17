<?php

namespace App\Services\Rag;

use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Services\AiAssistant\ProviderManager;
use App\Services\Rag\Contracts\VectorStore;
use Throwable;

/**
 * لایه‌ی orchestration پایپ‌لاین RAG — استخراج → تکه‌تکه‌کردن → embedding → VectorStore::upsert.
 * تنها جایی در کدبیس که این سه سرویس (TextExtractionService/ChunkingService/
 * ProviderManager::embed) را کنار هم صدا می‌زند؛ صف‌بندی (App\Jobs\IndexKnowledgeContent) و
 * قلاب‌های چرخه‌ی عمر مدل (Section 27's KnowledgeEntry/KnowledgeEntryAttachment save/upload) این
 * سرویس را صدا می‌زنند، نه برعکس.
 *
 * شکست embedding (کلیدی تنظیم نشده، تماس شبکه شکست خورد) هرگز اینجا بلعیده نمی‌شود — این متدها
 * throw می‌کنند و صدازننده (صفِ صف‌شده) مسئولِ لاگ/ری‌تِرای طبق قواعد صف است؛ اما هرگز نباید
 * KnowledgeBaseService::retrieveRelevant() یا تولید محتوا را مسدود کند — آن fallback جای دیگری
 * (KnowledgeBaseService خودش) تضمین می‌شود، نه اینجا.
 */
class IndexingService
{
    public function __construct(
        private readonly TextExtractionService $extractor,
        private readonly ChunkingService $chunker,
        private readonly ProviderManager $providerManager,
        private readonly VectorStore $vectorStore,
    ) {}

    /**
     * ایندکس‌کردنِ فیلد content خودِ یک KnowledgeEntry (نه پیوست‌هایش — آن‌ها indexAttachment است).
     */
    public function indexKnowledgeEntry(KnowledgeEntry $entry): void
    {
        $this->embedAndStore(
            chunkableType: 'KnowledgeEntry',
            chunkableId: $entry->id,
            knowledgeEntryId: $entry->id,
            locale: $entry->locale,
            text: trim((string) $entry->content),
        );
    }

    /**
     * استخراج متن پیوست، ذخیره‌ی وضعیت استخراج روی خودِ رکورد، سپس تکه‌تکه‌کردن/embedding.
     * شکستِ استخراج (PDF خراب، URL غیرقابل‌دسترس) یک throw نیست — روی extraction_status='failed'
     * ثبت می‌شود و ایندکس‌کردن با یک ایندکس خالی (حذفِ قطعه‌های قبلی) پایان می‌یابد؛ این تنها نقطه‌ای
     * است که «شکست» به‌جای throw، یک وضعیتِ ذخیره‌شده و قابل‌مشاهده در ادمین است.
     */
    public function indexAttachment(KnowledgeEntryAttachment $attachment): void
    {
        try {
            $text = $this->extractor->extractForAttachment($attachment);
        } catch (Throwable $e) {
            $attachment->forceFill([
                'extraction_status' => KnowledgeEntryAttachment::EXTRACTION_FAILED,
                'extraction_error' => $e->getMessage(),
            ])->save();

            $this->vectorStore->deleteForOwner('KnowledgeEntryAttachment', $attachment->id);

            return;
        }

        $attachment->forceFill([
            'extraction_status' => KnowledgeEntryAttachment::EXTRACTION_EXTRACTED,
            'extracted_text' => $text,
            'extracted_at' => now(),
            'extraction_error' => null,
        ])->save();

        $this->embedAndStore(
            chunkableType: 'KnowledgeEntryAttachment',
            chunkableId: $attachment->id,
            knowledgeEntryId: $attachment->knowledge_entry_id,
            locale: $attachment->knowledgeEntry?->locale,
            text: trim($text),
        );
    }

    /**
     * هر دو مسیر بالا (خودِ entry، یا یک پیوست) بعد از داشتنِ متنِ نهایی به همین‌جا می‌رسند —
     * تکه‌تکه‌کردن، embedding دسته‌ای (یک تماس API برای همه‌ی chunkهای این مالک، نه یکی‌یکی)، و
     * جایگزینیِ کامل قطعه‌های قبلی (VectorStore::upsert خودش delete-then-insert است).
     */
    private function embedAndStore(string $chunkableType, int $chunkableId, int $knowledgeEntryId, ?string $locale, string $text): void
    {
        $chunkTexts = $this->chunker->chunk($text);

        if ($chunkTexts === []) {
            $this->vectorStore->deleteForOwner($chunkableType, $chunkableId);

            return;
        }

        $vectors = $this->providerManager->embed($chunkTexts, $chunkableType, $chunkableId);
        $embeddingModel = $this->providerManager->resolveEmbeddingProvider()?->embedding_model;

        $chunks = [];
        foreach ($chunkTexts as $index => $chunkText) {
            $chunks[] = [
                'chunk_index' => $index,
                'text' => $chunkText,
                'char_count' => mb_strlen($chunkText),
                'embedding' => $vectors[$index],
                'embedding_model' => $embeddingModel,
                'embedding_dims' => count($vectors[$index]),
                'locale' => $locale,
            ];
        }

        $this->vectorStore->upsert($chunkableType, $chunkableId, $knowledgeEntryId, $chunks);
    }
}
