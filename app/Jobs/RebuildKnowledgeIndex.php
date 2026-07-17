<?php

namespace App\Jobs;

use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Services\Rag\IndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * بازسازیِ کامل ایندکس RAG — همه‌ی KnowledgeEntry ها (فیلد content) و همه‌ی
 * KnowledgeEntryAttachment ها دوباره استخراج/تکه‌تکه/embed می‌شوند. برای دکمه‌ی «Rebuild All
 * Indexes» در KnowledgeEntriesTable (RAG8) و برای رخداد تغییرِ ارائه‌دهنده‌ی embedding
 * (بردارهای تولیدشده با یک مدل قدیمی دیگر با بردارهای مدل تازه قابل‌مقایسه نیستند، پس باید همه
 * از نو ساخته شوند). شکستِ یک آیتم (PDF خراب، URL از دسترس خارج‌شده) کل بازسازی را متوقف نمی‌کند
 * — گزارش می‌شود و ادامه پیدا می‌کند، هم‌روحِ AgentAuditService که یک یافته‌ی بد کل ممیزی را
 * نمی‌ترکاند.
 */
class RebuildKnowledgeIndex implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(IndexingService $service): void
    {
        KnowledgeEntry::query()->chunkById(50, function ($entries) use ($service) {
            foreach ($entries as $entry) {
                try {
                    $service->indexKnowledgeEntry($entry);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });

        KnowledgeEntryAttachment::query()->chunkById(50, function ($attachments) use ($service) {
            foreach ($attachments as $attachment) {
                try {
                    $service->indexAttachment($attachment);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
