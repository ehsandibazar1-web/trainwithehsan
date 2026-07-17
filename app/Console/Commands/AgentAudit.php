<?php

namespace App\Console\Commands;

use App\Services\AiAgent\AgentAuditService;
use Illuminate\Console\Command;

/**
 * ممیزی هفتگیِ خودکار AI Agent — مثل articles:publish-due، مستقیم و همزمان در پردازش cron اجرا
 * می‌شود (نه صف‌شده) تا به روشن‌بودنِ queue worker وابسته نباشد؛ حجم محتوای این سایت کوچک است،
 * پس هزینه‌ی O(n²) دسته‌های جفتی (تاپیک تکراری/کانیبالیزیشن) اینجا مشکلی ایجاد نمی‌کند — نگاه
 * کنید به CLAUDE.md، بخش «AI Agent».
 */
class AgentAudit extends Command
{
    protected $signature = 'agent:audit';

    protected $description = 'اجرای ممیزی AI Agent و ذخیره‌ی توصیه‌های تازه';

    public function handle(AgentAuditService $service): int
    {
        $run = $service->generateAndPersist('scheduled');

        $this->info("Audit {$run->status}: {$run->found_count} found, {$run->new_count} new, {$run->resolved_count} resolved.");

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
