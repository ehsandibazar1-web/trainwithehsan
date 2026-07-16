<?php

namespace App\Jobs;

use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * اجرای صف‌شده‌ی ایمپورت API — همان سرویس، همان لاگ؛ فقط غیرهمزمان.
 * نتیجه (موفق یا ناموفق) مثل همیشه در import_logs ثبت می‌شود.
 */
class ImportAiArticle implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $raw,
        private readonly string $format = 'json',
        private readonly array $context = [],
    ) {}

    public function handle(ArticleImportService $service): void
    {
        $service->import($this->raw, $this->format, $this->context);
    }
}
