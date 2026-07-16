<?php

namespace App\Jobs;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\Page;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * یک ترجمه‌ی «کامل» می‌سازد: نه یک پیشنهاد متنی، بلکه یک ردیف Article/Page تازه و لینک‌شده
 * (translation_of)، همیشه status=draft — طبق همان سیاستِ «محتوای تولیدشده با هوش مصنوعی هرگز
 * خودکار منتشر نمی‌شود» که AI Import API با forceDraft() برایش دارد.
 *
 * برای Article از App\Services\ArticleImport\ArticleImportService::import() استفاده می‌شود —
 * دقیقاً همان مسیر ایمپورت موجود (اعتبارسنجی، اسلاگ، ImportLog، Activity Log)، نه یک مسیر ساخت
 * جدید. Page مدل ساده‌تری دارد و سرویس ایمپورت مخصوص خودش را ندارد؛ ساخت آن مستقیماً از طریق
 * Eloquent است (تا Activity Log هم ثبت شود) با همان تضمین یکتایی اسلاگ.
 *
 * فقط محتوای متنی (title/body/excerpt/faqs) از هوش مصنوعی می‌آید
 * (ContentAssistantService::buildTranslationPayload) — متادیتای غیرمحتوایی (locale،
 * translation_of، status، تصویر، دسته) همیشه همین‌جا و از روی رکورد اصلی ساخته می‌شود.
 */
class TranslateArticleDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private readonly string $contentType,
        private readonly int $contentId,
        private readonly string $targetLocale,
        private readonly int $generationId,
    ) {}

    public function handle(ContentAssistantService $service, ArticleImportService $importService): void
    {
        $generation = AiGeneration::find($this->generationId);

        if (! $generation) {
            return;
        }

        $generation->update(['status' => 'processing']);

        $record = $this->contentType === 'Article' ? Article::find($this->contentId) : Page::find($this->contentId);

        if (! $record) {
            $generation->update(['status' => 'failed', 'error' => 'The original content no longer exists.']);

            return;
        }

        try {
            $translated = $service->buildTranslationPayload($record, $this->targetLocale);

            $newRecord = $this->contentType === 'Article'
                ? $this->createTranslatedArticle($importService, $record, $translated)
                : $this->createTranslatedPage($record, $translated);

            $generation->update([
                'status' => 'completed',
                'result' => [
                    'id' => $newRecord->id,
                    'type' => $this->contentType,
                    'title' => $newRecord->title,
                    'edit_url' => $this->contentType === 'Article'
                        ? ArticleResource::getUrl('edit', ['record' => $newRecord->id])
                        : PageResource::getUrl('edit', ['record' => $newRecord->id]),
                ],
            ]);
        } catch (Throwable $e) {
            $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function createTranslatedArticle(ArticleImportService $importService, Article $source, array $translated): Article
    {
        // faqs عمداً فقط وقتی در payload گنجانده می‌شود که واقعاً داده‌ای دارد — ArticleImportService
        // با array_key_exists('faqs', ...) چک می‌کند، پس یک کلید faqs=null باعث خطای اعتبارسنجی
        // «Invalid FAQ» می‌شود، نه اینکه به‌سادگی نادیده گرفته شود
        $payload = array_filter([
            'locale' => $this->targetLocale,
            'translation_of' => $source->id,
            'title' => $translated['title'],
            'body' => $translated['body'],
            'excerpt' => $translated['excerpt'],
            'faqs' => $translated['faqs'],
            'category' => $source->category,
            'featured_image' => $source->image_path,
            'status' => 'draft',
            'author_name' => $source->author_name,
        ]);

        $result = $importService->import(json_encode($payload), 'json', ['source' => 'ai_translate']);

        if (! $result['article']) {
            throw new \RuntimeException(implode(' ', $result['errors']) ?: 'Translation import failed.');
        }

        return $result['article'];
    }

    private function createTranslatedPage(Page $source, array $translated): Page
    {
        return Page::create([
            'locale' => $this->targetLocale,
            'translation_of' => $source->id,
            'title' => $translated['title'],
            'slug' => $this->uniqueSlug(Page::makeSlug($translated['title'])),
            'body' => $translated['body'],
            'image_path' => $source->image_path,
            'status' => 'draft',
        ]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;

        while (Page::locale($this->targetLocale)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
