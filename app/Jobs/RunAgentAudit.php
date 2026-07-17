<?php

namespace App\Jobs;

use App\Services\AiAgent\AgentAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * اجرای صف‌شده‌ی ممیزی AI Agent — برای دکمه‌ی «Run Audit Now» در داشبورد (که در یک درخواست وب صدا
 * زده می‌شود و نباید بلاک شود، هم‌روح GenerateInternalLinkSuggestions). اجرای هفتگیِ خودکار از
 * طریق App\Console\Commands\AgentAudit است، نه این جاب — آن دستور مستقیماً و همزمان اجرا می‌شود
 * (مثل articles:publish-due) تا وابسته به روشن‌بودنِ queue worker نباشد؛ هر دو مسیر همان
 * AgentAuditService::generateAndPersist() را صدا می‌زنند، منطق تکرار نشده.
 */
class RunAgentAudit implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(AgentAuditService $service): void
    {
        $service->generateAndPersist('manual');
    }
}
