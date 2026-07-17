<?php

namespace App\Jobs;

use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Services\Rag\IndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * ایندکس‌کردنِ صف‌شده‌ی یک KnowledgeEntry (فیلد content خودش) یا یک KnowledgeEntryAttachment
 * (استخراج + ایندکس) — همان الگوی GenerateInternalLinkSuggestions/RunAgentAudit: یک تماس API
 * واقعی (embedding) در چرخه‌ی درخواست وب/ذخیره‌سازیِ فرم بلاک‌کننده نباشد. قلاب‌های چرخه‌ی عمرِ
 * KnowledgeEntry/KnowledgeEntryAttachment (Section 27/RAG6) این جاب را دیسپچ می‌کنند، نه
 * IndexingService را مستقیم صدا می‌زنند.
 *
 * هیچ‌گاه throw نمی‌کند: نبودِ ارائه‌دهنده‌ی embedding پیکربندی‌شده یک وضعیتِ کاملاً عادی است (نه
 * فقط یک fallback در زمانِ بازیابی، بلکه اینجا هم) — یک ادمین ممکن است هرگز Embeddings را در AI
 * Routing تنظیم نکند و فقط از بازیابیِ کلمه‌ایِ fallback در KnowledgeBaseService راضی باشد؛ در آن
 * حالت هر ذخیره‌ی KnowledgeEntry نباید صف را (یا در حالت sync، خودِ درخواست را) با یک خطا بترکاند.
 */
class IndexKnowledgeContent implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly KnowledgeEntry|KnowledgeEntryAttachment $subject) {}

    public function handle(IndexingService $service): void
    {
        try {
            if ($this->subject instanceof KnowledgeEntry) {
                $service->indexKnowledgeEntry($this->subject);

                return;
            }

            $service->indexAttachment($this->subject);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
