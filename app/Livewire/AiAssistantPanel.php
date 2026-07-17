<?php

namespace App\Livewire;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\PageResource;
use App\Jobs\GenerateHeroImage;
use App\Jobs\ProcessAiChatMessage;
use App\Jobs\RunAiContentGeneration;
use App\Jobs\TranslateArticleDraft;
use App\Models\AiChatMessage;
use App\Models\AiGeneration;
use App\Models\AiImageGeneration;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page as PageModel;
use App\Services\AiAssistant\ActionRegistry;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\AiAssistant\DiffService;
use App\Services\AiAssistant\GenerationApplier;
use App\Services\AiAssistant\ProviderManager;
use App\Services\Seo\SeoAuditService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * دستیار هوش مصنوعی برای یک مقاله/صفحه‌ی مشخص — اولین کامپوننت Livewire ساده‌ی این پروژه (نه یک
 * Filament\Pages\Page). دو جا mount می‌شود: (۱) درون سایدبار/کشوی تعبیه‌شده در صفحه‌ی ویرایش
 * (EditArticle/EditPage، standalone=false)، (۲) درون صفحه‌ی مستقل App\Filament\Pages\AiContentAssistant
 * که برای دسترسی مستقیم/لینک‌دهی نگه داشته شده (standalone=true). کل منطق generate/apply/restore
 * همان چیزی است که قبلاً در AiContentAssistant بود — اینجا فقط جابه‌جا شده، بازنویسی نشده.
 */
class AiAssistantPanel extends Component
{
    public string $recordType = 'Article';

    public int $recordId;

    public bool $standalone = false;

    public ?Model $record = null;

    public string $activeTab = 'generate'; // generate | review

    public string $chatInput = '';

    public function mount(string $recordType, int $recordId, bool $standalone = false): void
    {
        $this->recordType = $recordType;
        $this->recordId = $recordId;
        $this->standalone = $standalone;

        $this->record = $recordType === 'Article' ? Article::find($recordId) : PageModel::find($recordId);

        abort_if(! $this->record, 404);
    }

    public function render()
    {
        return view('livewire.ai-assistant-panel');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getFieldsProperty(): array
    {
        $fields = array_filter(
            ActionRegistry::applicableTo($this->recordType),
            fn (array $definition) => $definition['appliable'] ?? true,
        );

        return collect($fields)->map(function (array $definition, string $key) {
            $history = AiGeneration::forField($this->recordType, $this->record->id, $key)
                ->with('knowledgeEntries')
                ->latest()
                ->take(5)
                ->get();

            return array_merge($definition, [
                'key' => $key,
                'current_value' => in_array($key, ActionRegistry::MEDIA_BACKED_FIELDS, true)
                    ? $this->mediaForRecord()?->getAttribute($key)
                    : $this->record->getAttribute($key),
                'latest' => $history->first(),
                'history' => $history,
            ]);
        })->all();
    }

    // فیلدهای فقط-پیشنهادی (appliable=false) که در گرید اصلی Generate نشان داده نمی‌شوند — هر
    // کدام کارت خودشان را در پایین تب Generate دارند (نگاه کنید به livewire/ai-assistant-panel.blade.php)
    public function getSuggestionFieldsProperty(): array
    {
        return collect(['internal_links', 'external_links', 'schema'])
            ->mapWithKeys(function (string $key) {
                $definition = ActionRegistry::for($key);

                if (! in_array($this->recordType, $definition['applicable_to'], true)) {
                    return [];
                }

                return [$key => array_merge($definition, [
                    'key' => $key,
                    'latest' => AiGeneration::forField($this->recordType, $this->record->id, $key)->latest()->first(),
                ])];
            })->all();
    }

    private function mediaForRecord(): ?Media
    {
        return Media::forRecord($this->record);
    }

    public function getReviewFindingsProperty(): array
    {
        return app(ContentReviewService::class)->review($this->record);
    }

    public function getScoreCardProperty(): array
    {
        return app(ContentReviewService::class)->scoreCard($this->record);
    }

    // پیش‌نمایش دیف قرمز/سبز قبل از Apply — فقط برای مقادیر متنی ساده معنی دارد (عنوان/توضیحات/...)؛
    // برای مقادیر آرایه‌ای (FAQ، برچسب‌ها، پیشنهادهای لینک و ...) null برمی‌گرداند تا نمای فعلیِ
    // فهرستی/QA همان‌طور که بود نمایش داده شود
    public function diffFor(mixed $currentValue, mixed $result): ?array
    {
        if (is_array($result) || is_array($currentValue)) {
            return null;
        }

        return app(DiffService::class)->diffWords((string) $currentValue, (string) $result);
    }

    public function getReviewSummaryProperty(): ?AiGeneration
    {
        return AiGeneration::forField($this->recordType, $this->record->id, 'content_review_summary')
            ->latest()
            ->first();
    }

    public function generateReviewSummary(): void
    {
        $this->generateField('content_review_summary', 'generate');
    }

    public function getIsPollingProperty(): bool
    {
        return AiGeneration::where('content_type', $this->recordType)
            ->where('content_id', $this->record->id)
            ->whereIn('status', ['queued', 'processing'])
            ->exists()
            || AiImageGeneration::forRecord($this->recordType, $this->record->id)
                ->whereIn('status', ['queued', 'processing'])
                ->exists();
    }

    // ============ AI Chat ============

    public function getChatMessagesProperty(): Collection
    {
        return AiChatMessage::forRecord($this->recordType, $this->record->id)
            ->with('relatedGeneration')
            ->orderBy('created_at')
            ->get();
    }

    // وقتی آخرین پیام از کاربر است و هنوز پاسخ assistant نیامده، یعنی ProcessAiChatMessage هنوز
    // در صف/در‌حال‌اجراست — سایدبار در این حالت هم باید poll کند (جدا از isPolling که فقط
    // AiGeneration را می‌بیند، نه پیام‌های چت)
    public function getIsChatPendingProperty(): bool
    {
        return $this->chatMessages->last()?->role === 'user';
    }

    public function sendChatMessage(): void
    {
        $message = trim($this->chatInput);

        if ($message === '') {
            return;
        }

        $userMessage = AiChatMessage::create([
            'content_type' => $this->recordType,
            'content_id' => $this->record->id,
            'role' => 'user',
            'message' => $message,
        ]);

        $this->chatInput = '';

        ProcessAiChatMessage::dispatch($this->recordType, $this->record->id, $userMessage->id);
    }

    public function generateField(string $field, string $mode): void
    {
        $this->queueGeneration($field, $mode);
        $this->notifyQueued('Generation');
    }

    // چهار دکمه‌ی سریع روی متن بدنه — همان generateField روی فیلد body با یک حالت ثابت، فقط یک
    // میان‌بر برای Quick Actions
    public function quickBodyAction(string $mode): void
    {
        $this->queueGeneration('body', $mode);
        $this->notifyQueued('Article body ('.$mode.')');
    }

    // میان‌بر Quick Actions «SEO Only» — فقط چهار فیلد سئو/OG و اسلاگ را صف می‌کند، نه همه‌چیز
    public function quickSeoOnly(): void
    {
        foreach (['seo_title', 'meta_description', 'og_title', 'og_description', 'slug'] as $key) {
            if (in_array($this->recordType, ActionRegistry::for($key)['applicable_to'], true)) {
                $this->queueGeneration($key, 'generate');
            }
        }

        $this->notifyQueued('SEO fields');
    }

    // میان‌بر Quick Actions «FAQ Only» — فقط برای Article معنی دارد (ActionRegistry همین را برای Page رد می‌کند)
    public function quickFaqOnly(): void
    {
        $this->queueGeneration('faq', 'generate');
        $this->notifyQueued('FAQ');
    }

    // دکمه‌ی اصلی «✨ Optimize Entire Article» / Quick Actions «Generate Everything» — عمداً همان
    // یک عمل است (طبق درخواست کاربر، فقط زیر دو عنوان متفاوت)؛ هر فیلد قابل‌تولیدِ این نوع رکورد را
    // صف می‌کند، به‌جز content_review_summary (که دکمه‌ی مخصوص خودش را در تب Review دارد) و بدنه
    // (که حالت generate ندارد — نگاه کنید به ActionRegistry). هیچ‌کدام خودکار Apply نمی‌شوند.
    public function optimizeEntireArticle(): void
    {
        foreach (ActionRegistry::applicableTo($this->recordType) as $key => $definition) {
            if ($key === 'content_review_summary' || ! in_array('generate', $definition['modes'], true)) {
                continue;
            }

            $this->queueGeneration($key, 'generate');
        }

        $this->notifyQueued('Every suggestion for this article');
    }

    // پیشرفتِ تقریبیِ یک دسته‌ی تولید گروهی («۳ از ۱۴ انجام شد») — بدون ستون batch_id، فقط از
    // تعداد تولیدهای صف‌شده/در‌حال‌اجرا در برابر تعداد کل تولیدهای همین رکورد در ۵ دقیقه‌ی اخیر
    public function getGenerationProgressProperty(): ?string
    {
        $pending = AiGeneration::where('content_type', $this->recordType)
            ->where('content_id', $this->record->id)
            ->whereIn('status', ['queued', 'processing'])
            ->count();

        if ($pending === 0) {
            return null;
        }

        $recentTotal = AiGeneration::where('content_type', $this->recordType)
            ->where('content_id', $this->record->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        return max(0, $recentTotal - $pending).' of '.$recentTotal.' done';
    }

    // ============ Translate (full draft, not a text suggestion — see App\Jobs\TranslateArticleDraft) ============

    public function translate(string $targetLocale): void
    {
        $generation = AiGeneration::create([
            'content_type' => $this->recordType,
            'content_id' => $this->record->id,
            'field' => 'translate',
            'mode' => $targetLocale,
            'provider' => config('services.anthropic.driver', 'anthropic'),
            'status' => 'queued',
        ]);

        TranslateArticleDraft::dispatch($this->recordType, $this->record->id, $targetLocale, $generation->id);

        $this->notifyQueued('Translation to '.strtoupper($targetLocale));
    }

    public function getTranslationsProperty(): Collection
    {
        return AiGeneration::forField($this->recordType, $this->record->id, 'translate')
            ->latest()
            ->get();
    }

    // ============ Hero Image Generation (App\Jobs\GenerateHeroImage — see CLAUDE.md, AI Image Pipeline) ============

    // آیا اصلاً یک ارائه‌دهنده‌ی تولید تصویرِ قابل‌استفاده تنظیم شده — دکمه‌ی «Generate Hero Image»
    // فقط وقتی این true است فعال می‌شود، وگرنه یک پیام راهنما به‌جایش نشان داده می‌شود
    public function getCanGenerateImagesProperty(): bool
    {
        return app(ProviderManager::class)->resolveImageProvider() !== null;
    }

    public function getHeroImageGenerationsProperty(): Collection
    {
        return AiImageGeneration::forRecord($this->recordType, $this->record->id)
            ->with('media')
            ->latest()
            ->take(5)
            ->get();
    }

    // برخلاف generateField/queueGeneration (که یک AiGeneration دو-مرحله‌ای preview-then-apply
    // می‌سازد)، اینجا یک AiImageGeneration جداگانه است — تصویر همان لحظه که جاب تمام شود ذخیره و
    // featured image می‌شود، بدون کلیک Apply جدا (هم‌روحِ Translate بالا)
    public function generateHeroImage(): void
    {
        if (! $this->canGenerateImages) {
            Notification::make()->danger()->title('No image-generation provider is configured')->body('Set one up in AI Studio → AI Routing → Image Generation.')->send();

            return;
        }

        $generation = AiImageGeneration::create([
            'content_type' => $this->recordType,
            'content_id' => $this->record->id,
            'prompt_field' => 'hero_image_prompt',
            'status' => 'queued',
            'user_id' => Auth::id(),
        ]);

        GenerateHeroImage::dispatch($generation->id);

        $this->notifyQueued('Hero image generation');
    }

    public function cancelImageGeneration(int $id): void
    {
        $generation = AiImageGeneration::find($id);

        if (! $generation || ! $generation->isCancellable()) {
            return;
        }

        $generation->update(['status' => 'cancelled']);

        Notification::make()->success()->title('Image generation cancelled')->send();
    }

    // ============ Cancellation ============

    // این سقفِ واقعیِ چیزی است که روی صف database ممکن است — نمی‌شود یک تماس HTTP در حال اجرا را
    // واقعاً کشت. اگر هنوز queued است، هرگز اجرا نمی‌شود (چک‌پوینت اول در خودِ جاب). اگر
    // processing است، جاب هنوز تا انتهای تماس فعلی ادامه می‌دهد ولی نتیجه‌اش را نمی‌نویسد
    // (چک‌پوینت دوم) — یعنی «کنسل» یعنی «نتیجه‌اش را نادیده بگیر»، نه «همین الان متوقفش کن».
    public function cancelGeneration(int $id): void
    {
        $generation = AiGeneration::find($id);

        if (! $generation || ! $generation->isCancellable()) {
            return;
        }

        $generation->update(['status' => 'cancelled']);

        Notification::make()->success()->title('Generation cancelled')->send();
    }

    // ============ History — همه‌ی تولیدهای این رکورد در همه‌ی فیلدها، نه فقط ۴ تای آخرِ هر فیلد ============

    public function getHistoryProperty(): Collection
    {
        return AiGeneration::forRecord($this->recordType, $this->record->id)
            ->with('knowledgeEntries')
            ->latest()
            ->take(30)
            ->get();
    }

    private function queueGeneration(string $field, string $mode): void
    {
        // فیلدهای MEDIA_BACKED_FIELDS روی رکورد Article/Page ذخیره نمی‌شوند، روی Media متناظر —
        // پس مقدار فعلی هم باید از آنجا خوانده شود
        $inputSnapshot = in_array($field, ActionRegistry::MEDIA_BACKED_FIELDS, true)
            ? $this->mediaForRecord()?->getAttribute($field)
            : $this->record->getAttribute($field);

        $generation = AiGeneration::create([
            'user_id' => Auth::id(),
            'content_type' => $this->record->getMorphClass(),
            'content_id' => $this->record->id,
            'field' => $field,
            'mode' => $mode,
            'provider' => config('services.anthropic.driver', 'anthropic'),
            'status' => 'queued',
            'input_snapshot' => $inputSnapshot,
        ]);

        RunAiContentGeneration::dispatch($generation->id);
    }

    private function notifyQueued(string $what): void
    {
        Notification::make()
            ->success()
            ->title($what.' queued')
            ->body('This runs in the background — a queue worker must be running (php artisan queue:work) for it to complete. This page refreshes automatically while it runs.')
            ->persistent()
            ->send();
    }

    public function applyGeneration(int $id): void
    {
        $generation = AiGeneration::find($id);

        if (! $generation || ! $generation->canApply()) {
            return;
        }

        if (in_array($generation->field, ActionRegistry::MEDIA_BACKED_FIELDS, true) && ! $this->mediaForRecord()) {
            Notification::make()->danger()->title('No Media Library entry found for this image')->send();

            return;
        }

        app(GenerationApplier::class)->apply($generation, $this->record);

        Notification::make()->success()->title('Applied to '.ActionRegistry::for($generation->field)['label'])->send();
    }

    public function restoreGeneration(int $id): void
    {
        $generation = AiGeneration::find($id);

        if (! $generation || ! $generation->canRestore()) {
            return;
        }

        app(GenerationApplier::class)->restore($generation, $this->record);

        Notification::make()->success()->title('Restored previous value for '.ActionRegistry::for($generation->field)['label'])->send();
    }

    // پیشنهادهای لینک داخلیِ هوش‌مصنوعی را به همان جدول/چرخه‌ی موجود
    // (App\Models\InternalLinkSuggestion، تایید/رد در Internal Linking Center) اضافه می‌کند —
    // منطق نوشتن در App\Services\AiAssistant\GenerationApplier است (اینجا و داشبورد AI Agent هر دو
    // همان را صدا می‌زنند)، اینجا فقط پیام موفقیت را نمایش می‌دهد
    public function applyInternalLinkSuggestions(int $id): void
    {
        $generation = AiGeneration::find($id);

        if (! $generation || ! $generation->canApply() || $generation->field !== 'internal_links') {
            return;
        }

        $count = app(GenerationApplier::class)->applyInternalLinkSuggestions($generation, $this->record);

        Notification::make()
            ->success()
            ->title($count.' internal link suggestion(s) added')
            ->body('Review and approve them in the Internal Linking Center, same as rule-based suggestions.')
            ->send();
    }

    // هر URL پیشنهادی هوش مصنوعی، قبل از نمایش، با همان الگوی
    // SeoAuditService::checkExternalLinks() بررسی می‌شود که واقعاً بالا باشد
    public function getVerifiedExternalLinksProperty(): array
    {
        $generation = AiGeneration::forField($this->recordType, $this->record->id, 'external_links')->latest()->first();

        if (! $generation || ! $generation->canApply()) {
            return [];
        }

        $urls = collect($generation->result)->pluck('url')->filter()->all();

        if ($urls === []) {
            return [];
        }

        $checked = app(SeoAuditService::class)->checkUrls($urls);

        return collect($generation->result)->map(fn (array $item) => array_merge($item, [
            'broken' => $checked[$item['url']]['broken'] ?? false,
        ]))->all();
    }

    public function resolveTargetLabel(string $type, int $id): string
    {
        $model = $type === 'Article' ? Article::find($id) : PageModel::find($id);

        return $model ? $model->title : "#{$id} (not found)";
    }

    public function editUrl(): string
    {
        return $this->recordType === 'Article'
            ? ArticleResource::getUrl('edit', ['record' => $this->record->id])
            : PageResource::getUrl('edit', ['record' => $this->record->id]);
    }
}
