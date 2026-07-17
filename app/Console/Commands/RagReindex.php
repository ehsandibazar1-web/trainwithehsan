<?php

namespace App\Console\Commands;

use App\Models\KnowledgeEntry;
use App\Services\Rag\IndexingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * اجرای دستیِ همزمانِ بازسازی ایندکس RAG — هم‌روح media:backfill (نه صف‌شده، مستقیم در همین
 * پردازش اجرا می‌شود) چون این دستور برای اجرا از طریق CLI/dev در نظر گرفته شده، نه از داخل
 * پنل ادمین (که به‌جایش دکمه‌ی «Rebuild All Indexes» جاب صف‌شده‌ی App\Jobs\RebuildKnowledgeIndex
 * را دیسپچ می‌کند — این پروژه روی هاستِ بدون SSH اجرا می‌شود، پس مسیر واقعاً قابل‌استفاده در
 * production همان دکمه‌ی پنل است، این دستور برای local/staging است).
 */
class RagReindex extends Command
{
    protected $signature = 'rag:reindex {--entry= : Only reindex the KnowledgeEntry with this ID}';

    protected $description = 'Rebuild the RAG vector index (extract, chunk, embed) for all Knowledge Base entries and attachments, or one entry via --entry=ID.';

    public function handle(IndexingService $service): int
    {
        $entryId = $this->option('entry');

        $entries = $entryId
            ? KnowledgeEntry::query()->whereKey($entryId)->get()
            : KnowledgeEntry::query()->get();

        if ($entryId && $entries->isEmpty()) {
            $this->error("No KnowledgeEntry found with id {$entryId}.");

            return self::FAILURE;
        }

        $indexed = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            try {
                $service->indexKnowledgeEntry($entry);
                $indexed++;
            } catch (Throwable $e) {
                $this->warn("Failed to index KnowledgeEntry #{$entry->id} ({$entry->title}): {$e->getMessage()}");
                $failed++;
            }

            foreach ($entry->attachments as $attachment) {
                try {
                    $service->indexAttachment($attachment);
                    $indexed++;
                } catch (Throwable $e) {
                    $this->warn("Failed to index attachment #{$attachment->id} ({$attachment->original_filename}): {$e->getMessage()}");
                    $failed++;
                }
            }
        }

        $this->info("Indexed {$indexed} item(s), {$failed} failure(s).");

        return self::SUCCESS;
    }
}
