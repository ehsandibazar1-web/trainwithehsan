<?php

namespace App\Console\Commands;

use App\Models\Article;
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

        foreach ($due as $article) {
            $article->update(['status' => 'published']);
            $this->info("Published: [{$article->locale}] {$article->title}");
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
