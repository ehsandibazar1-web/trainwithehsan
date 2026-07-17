<?php

namespace App\Services\Rag\Contracts;

/**
 * قرارداد ذخیره/بازیابیِ بردار — تنها نقطه‌ای که بقیه‌ی کد (IndexingService، KnowledgeBaseService)
 * با آن کار می‌کند؛ هیچ‌جای دیگری مستقیماً به App\Models\KnowledgeChunk یا SQLite وابسته نیست.
 * پیاده‌سازیِ امروز (App\Services\Rag\VectorStores\EloquentCosineVectorStore) شباهت کسینوسی را
 * دستی در PHP روی SQLite محاسبه می‌کند؛ اگر بعداً به pgvector/Qdrant/... مهاجرت شد، فقط یک
 * پیاده‌سازیِ تازه‌ی همین اینترفیس لازم است + یک تغییر در بایندینگ سرویس (AppServiceProvider) —
 * نگاه کنید به CLAUDE.md بخش RAG.
 */
interface VectorStore
{
    /**
     * جایگزینیِ کاملِ قطعه‌های یک مالک (یک KnowledgeEntry یا KnowledgeEntryAttachment) — قطعه‌های
     * قدیمی حذف و قطعه‌های تازه درج می‌شوند؛ برای «rebuild index» و ایندکس‌سازیِ عادی هر دو یکسان.
     *
     * @param  array<int, array{chunk_index:int, text:string, char_count:int, embedding:float[], embedding_model:string, embedding_dims:int, locale:?string}>  $chunks
     */
    public function upsert(string $chunkableType, int $chunkableId, int $knowledgeEntryId, array $chunks): void;

    public function deleteForOwner(string $chunkableType, int $chunkableId): void;

    /**
     * جست‌وجوی نزدیک‌ترین قطعه‌ها بر اساس شباهت کسینوسی. $filters عمداً فقط شرط‌های سطح-پایین
     * می‌گیرد (کدام entry idها، کدام زبان) — منطق کسب‌وکار «کدام entry اصلا available است»
     * (status/expires_at) در KnowledgeBaseService محاسبه و به شکل knowledge_entry_id_in داده
     * می‌شود، نه اینجا؛ VectorStore چیزی از قوانین KnowledgeEntry نمی‌داند.
     *
     * @param  float[]  $queryEmbedding
     * @param  array{knowledge_entry_id_in?: int[], locale?: ?string}  $filters
     * @return array<int, array{chunk_id:int, knowledge_entry_id:int, chunkable_type:string, chunkable_id:int, chunk_index:int, text:string, score:float}>
     */
    public function search(array $queryEmbedding, int $limit, array $filters = []): array;

    public function count(): int;
}
