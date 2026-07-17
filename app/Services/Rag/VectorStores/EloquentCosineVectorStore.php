<?php

namespace App\Services\Rag\VectorStores;

use App\Models\KnowledgeChunk;
use App\Services\Rag\Contracts\VectorStore;
use Illuminate\Support\Facades\DB;

/**
 * پیاده‌سازیِ امروزِ VectorStore — بدون هیچ سرویس بیرونی: قطعه‌ها و بردارهایشان در جدول
 * knowledge_chunks (JSON) ذخیره می‌شوند، جست‌وجو یک اسکن خطی است که شباهت کسینوسی را در PHP
 * محاسبه می‌کند. در حجم محتوای این پروژه (نگاه کنید به CLAUDE.md، «Performance Rules») این کاملاً
 * کافی است؛ اگر روزی حجم واقعاً مشکل شد، فقط این کلاس با یک VectorStore دیگر (مثلاً
 * pgvector-backed) جایگزین می‌شود — بقیه‌ی کد (IndexingService، KnowledgeBaseService) هیچ تغییری
 * نمی‌خواهد چون فقط با اینترفیس کار می‌کنند.
 */
class EloquentCosineVectorStore implements VectorStore
{
    public function upsert(string $chunkableType, int $chunkableId, int $knowledgeEntryId, array $chunks): void
    {
        DB::transaction(function () use ($chunkableType, $chunkableId, $knowledgeEntryId, $chunks) {
            $this->deleteForOwner($chunkableType, $chunkableId);

            if ($chunks === []) {
                return;
            }

            $now = now();
            $rows = array_map(fn (array $chunk) => [
                'chunkable_type' => $chunkableType,
                'chunkable_id' => $chunkableId,
                'knowledge_entry_id' => $knowledgeEntryId,
                'chunk_index' => $chunk['chunk_index'],
                'text' => $chunk['text'],
                'char_count' => $chunk['char_count'],
                'embedding' => json_encode($chunk['embedding']),
                'embedding_model' => $chunk['embedding_model'],
                'embedding_dims' => $chunk['embedding_dims'],
                'locale' => $chunk['locale'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunks);

            KnowledgeChunk::insert($rows);
        });
    }

    public function deleteForOwner(string $chunkableType, int $chunkableId): void
    {
        KnowledgeChunk::where('chunkable_type', $chunkableType)
            ->where('chunkable_id', $chunkableId)
            ->delete();
    }

    public function search(array $queryEmbedding, int $limit, array $filters = []): array
    {
        $query = KnowledgeChunk::query();

        if (! empty($filters['knowledge_entry_id_in'])) {
            $query->whereIn('knowledge_entry_id', $filters['knowledge_entry_id_in']);
        }

        if (array_key_exists('locale', $filters) && $filters['locale'] !== null) {
            $query->where('locale', $filters['locale']);
        }

        $candidates = $query->get(['id', 'knowledge_entry_id', 'chunkable_type', 'chunkable_id', 'chunk_index', 'text', 'embedding']);

        if ($candidates->isEmpty()) {
            return [];
        }

        return $candidates
            ->map(fn (KnowledgeChunk $chunk) => [
                'chunk_id' => $chunk->id,
                'knowledge_entry_id' => $chunk->knowledge_entry_id,
                'chunkable_type' => $chunk->chunkable_type,
                'chunkable_id' => $chunk->chunkable_id,
                'chunk_index' => $chunk->chunk_index,
                'text' => $chunk->text,
                'score' => $this->cosineSimilarity($queryEmbedding, $chunk->embedding ?? []),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    public function count(): int
    {
        return KnowledgeChunk::count();
    }

    /** @param  float[]  $a @param float[] $b */
    private function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
