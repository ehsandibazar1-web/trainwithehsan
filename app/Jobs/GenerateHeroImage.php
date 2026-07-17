<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Models\AiImageGeneration;
use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Page;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\AiAssistant\GenerationApplier;
use App\Services\AiAssistant\ProviderManager;
use App\Services\Media\MediaProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * «Generate Hero Image» یک‌کلیکی — طبق تصمیم تأییدشده‌ی کاربر («فقط یک تصویر با کیفیت بالا»):
 * یک تصویر واقعی تولید می‌کند و همان لحظه featured image می‌شود (نه یک پیشنهاد دو-مرحله‌ای مثل
 * بقیه‌ی فیلدهای ActionRegistry) — هم‌روحِ App\Jobs\TranslateArticleDraft که «تولید» و «ساختن
 * چیز واقعی» یک عمل است. سپس، طبق «Automatically: ... Generate ALT, Generate Caption, Generate
 * SEO metadata»، چند فیلد متنیِ کوچکِ دیگر را هم خودکار تولید/اعمال می‌کند — هر کدام از طریق
 * همان مسیرِ کاملاً موجود (ContentAssistantService::generate() + یک AiGeneration واقعی +
 * GenerationApplier::apply()) تا در تب History هم دیده شوند، نه یک مسیر نوشتنِ دوم.
 *
 * دو چک‌پوینتِ کنسل‌شدن، هم‌روحِ RunAiContentGeneration/TranslateArticleDraft: قبل از شروع، و
 * درست قبل از ذخیره‌ی تصویر (بعد از تماس API که ممکن است طول بکشد).
 */
class GenerateHeroImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // فیلدهای متنیِ خودکار پس از تولید تصویر — همیشه (چون تصویر تازه است، چیزی برای حفظ‌کردن
    // نیست) در برابر فیلدهای سئو که فقط وقتی خالی‌اند پر می‌شوند (محتوای دستیِ موجود هرگز بازنویسی نمی‌شود)
    private const ALWAYS_FIELDS = ['alt_text', 'caption', 'description'];

    private const BLANK_ONLY_FIELDS = ['seo_title', 'meta_description', 'og_title', 'og_description'];

    public function __construct(private readonly int $imageGenerationId) {}

    public function handle(ProviderManager $providerManager, ContentAssistantService $contentService, MediaProcessor $mediaProcessor, GenerationApplier $applier): void
    {
        $generation = AiImageGeneration::find($this->imageGenerationId);

        if (! $generation || $generation->status === 'cancelled') {
            return;
        }

        $generation->update(['status' => 'processing']);

        $record = $generation->content_type === 'Article' ? Article::find($generation->content_id) : Page::find($generation->content_id);

        if (! $record) {
            $generation->update(['status' => 'failed', 'error' => 'The article/page no longer exists.']);

            return;
        }

        try {
            $prompt = $this->resolvePrompt($record);
            $generation->update(['prompt_used' => $prompt]);

            $result = $providerManager->generateImage($prompt, [], $record->getMorphClass(), $record->id);

            if ($generation->fresh()->status === 'cancelled') {
                return;
            }

            $media = $this->saveToMediaLibrary($mediaProcessor, $record, $result);

            $record->update(['image_path' => $media->disk_path]);

            $generation->update([
                'status' => 'completed',
                'provider_slug' => $result['provider_slug'],
                'model' => $result['model'] ?? null,
                'media_id' => $media->id,
            ]);

            $this->autoGenerateMetadata($contentService, $applier, $record->fresh());
        } catch (Throwable $e) {
            if ($generation->fresh()->status === 'cancelled') {
                return;
            }

            $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function resolvePrompt(Article|Page $record): string
    {
        if (filled($record->hero_image_prompt)) {
            return $record->hero_image_prompt;
        }

        $parts = array_filter([
            'A professional, editorial-quality photograph for a self-defense/Brazilian Jiu-Jitsu article titled "'.$record->title.'".',
            $record instanceof Article && filled($record->category) ? 'Category: '.$record->category.'.' : null,
            filled($record->excerpt ?? null) ? Str::limit(strip_tags($record->excerpt), 200) : null,
        ]);

        return implode(' ', $parts);
    }

    /** @param  array{bytes: string, mime_type: string, revised_prompt: ?string, provider_slug: string, model: ?string}  $result */
    private function saveToMediaLibrary(MediaProcessor $mediaProcessor, Article|Page $record, array $result): Media
    {
        $extension = match (true) {
            str_contains($result['mime_type'], 'png') => 'png',
            str_contains($result['mime_type'], 'jpeg') => 'jpg',
            default => 'webp',
        };

        $tmp = tempnam(sys_get_temp_dir(), 'aihero');
        file_put_contents($tmp, $result['bytes']);

        // فایل باینریِ دریافتی از API را به یک UploadedFile واقعی تبدیل می‌کند (همان الگوی
        // ArticleImportService::downloadImage — پرچم test=true برای فایل‌های ساخته‌شده به‌صورت
        // برنامه‌نویسی‌شده) تا از همان MediaProcessor استفاده شود که هر مسیر آپلود دیگر استفاده می‌کند
        $uploadedFile = new UploadedFile($tmp, Str::slug($record->title).'-hero.'.$extension, $result['mime_type'], null, true);

        $folder = MediaFolder::firstOrCreate(['name' => 'AI Generated', 'parent_id' => null]);

        $media = $mediaProcessor->store($uploadedFile, 'ai-generated', 'public', $folder->id);

        @unlink($tmp);

        return $media;
    }

    private function autoGenerateMetadata(ContentAssistantService $contentService, GenerationApplier $applier, Article|Page $record): void
    {
        foreach (self::ALWAYS_FIELDS as $field) {
            $this->autoGenerateField($contentService, $applier, $record, $field);
        }

        foreach (self::BLANK_ONLY_FIELDS as $field) {
            if (blank($record->getAttribute($field))) {
                $this->autoGenerateField($contentService, $applier, $record, $field);
            }
        }
    }

    // هر فیلدِ خودکار جدا try/catch می‌شود — شکستِ یکی (مثلا rate limit روی seo_title) نباید
    // تصویرِ از قبل ذخیره‌شده و featured-image-شده را «شکست‌خورده» نشان دهد
    private function autoGenerateField(ContentAssistantService $contentService, GenerationApplier $applier, Article|Page $record, string $field): void
    {
        try {
            $outcome = $contentService->generate($record, $field, 'generate');

            if ($outcome['result'] === null) {
                return;
            }

            $aiGeneration = AiGeneration::create([
                'content_type' => $record->getMorphClass(),
                'content_id' => $record->id,
                'field' => $field,
                'mode' => 'generate',
                'status' => 'completed',
                'result' => $outcome['result'],
            ]);

            $applier->apply($aiGeneration, $record);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
