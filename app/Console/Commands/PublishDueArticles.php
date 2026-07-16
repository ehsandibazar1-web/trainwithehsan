<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\WorkflowStage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishDueArticles extends Command
{
    protected $signature = 'articles:publish-due';

    protected $description = 'انتشار خودکار مقالات زمان‌بندی‌شده‌ای که زمانشان رسیده است';

    public function handle(): int
    {
        $due = Article::where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        $publishedStage = WorkflowStage::findBySlug(WorkflowStage::STAGE_PUBLISHED);

        foreach ($due as $article) {
            $article->update(['status' => 'published']);
            $this->info("Published: [{$article->locale}] {$article->title}");

            // اگر این مقاله از یک کارت برنامه‌ریز مادیت پیدا کرده باشد، مرحله‌ی آن کارت را هم
            // همگام می‌کنیم — بدون این، کارت در Kanban برای همیشه در «Scheduled» باقی می‌ماند
            // حتی بعد از انتشار خودکار توسط این دستور
            if ($publishedStage) {
                optional($article->contentPlan)->moveToStage($publishedStage);
            }
        }

        if ($due->isNotEmpty()) {
            // پاک‌سازی کش تا مقاله بلافاصله در صفحه اصلی و بلاگ دیده شود
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            $this->info("Cache cleared after publishing {$due->count()} article(s).");
        } else {
            $this->info('No due articles.');
        }

        return self::SUCCESS;
    }
}
