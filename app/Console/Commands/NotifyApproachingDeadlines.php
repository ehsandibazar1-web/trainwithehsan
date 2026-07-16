<?php

namespace App\Console\Commands;

use App\Models\ContentPlan;
use App\Models\WorkflowStage;
use App\Notifications\DeadlineApproaching;
use Illuminate\Console\Command;

/**
 * هشدار مهلت‌های نزدیک (due_at طی ۲۴ ساعت آینده) کارت‌هایی که هنوز منتشر/بایگانی نشده‌اند —
 * دقیقاً همان الگوی PublishDueArticles (یک دستور ساده، بدون صف). deadline_notified_at از
 * ارسال تکراری هر بار که این دستور اجرا می‌شود جلوگیری می‌کند (نگاه کنید به
 * App\Models\ContentPlan::booted()).
 */
class NotifyApproachingDeadlines extends Command
{
    protected $signature = 'content-plans:notify-deadlines';

    protected $description = 'اعلان مهلت‌های نزدیکِ کارت‌های برنامه‌ریز محتوا که هنوز منتشر نشده‌اند';

    public function handle(): int
    {
        $excludedStageIds = array_filter([
            WorkflowStage::findBySlug(WorkflowStage::STAGE_PUBLISHED)?->id,
            WorkflowStage::findBySlug(WorkflowStage::STAGE_ARCHIVED)?->id,
        ]);

        $due = ContentPlan::query()
            ->whereNotNull('due_at')
            ->whereNull('deadline_notified_at')
            ->where('due_at', '<=', now()->addDay())
            ->where('due_at', '>=', now())
            ->when($excludedStageIds !== [], fn ($q) => $q->whereNotIn('workflow_stage_id', $excludedStageIds))
            ->get();

        foreach ($due as $plan) {
            $recipient = $plan->assignee ?: $plan->author;

            if ($recipient) {
                $recipient->notify(new DeadlineApproaching($plan));
            }

            $plan->update(['deadline_notified_at' => now()]);
        }

        $this->info("Notified {$due->count()} approaching deadline(s).");

        return self::SUCCESS;
    }
}
