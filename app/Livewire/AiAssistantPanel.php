<?php

namespace App\Livewire;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\PageResource;
use App\Jobs\RunAiContentGeneration;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Media;
use App\Models\Page as PageModel;
use App\Services\AiAssistant\ActionRegistry;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\Seo\SeoAuditService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
                ->latest()
                ->take(5)
                ->get();

            return array_merge($definition, [
                'key' => $key,
                'current_value' => $key === 'alt_text' ? $this->mediaForRecord()?->alt_text : $this->record->getAttribute($key),
                'latest' => $history->first(),
                'history' => $history,
            ]);
        })->all();
    }

    // فیلدهای فقط-پیشنهادی (appliable=false) که در گرید اصلی Generate نشان داده نمی‌شوند — هر
    // کدام کارت خودشان را در پایین تب Generate دارند (نگاه کنید به livewire/ai-assistant-panel.blade.php)
    public function getSuggestionFieldsProperty(): array
    {
        return collect(['internal_links', 'external_links', 'schema', 'caption'])
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
        if (blank($this->record->image_path)) {
            return null;
        }

        return Media::where('disk_path', $this->record->image_path)->first();
    }

    public function getReviewFindingsProperty(): array
    {
        return app(ContentReviewService::class)->review($this->record);
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
            ->exists();
    }

    public function generateField(string $field, string $mode): void
    {
        // ALT روی رکورد Article/Page ذخیره نمی‌شود، روی Media متناظر — پس مقدار فعلی هم باید از آنجا خوانده شود
        $inputSnapshot = $field === 'alt_text' ? $this->mediaForRecord()?->alt_text : $this->record->getAttribute($field);

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

        Notification::make()
            ->success()
            ->title('Generation queued')
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

        if ($generation->field === 'alt_text') {
            $media = $this->mediaForRecord();

            if (! $media) {
                Notification::make()->danger()->title('No Media Library entry found for this image')->send();

                return;
            }

            $media->update(['alt_text' => $generation->result]);
        } else {
            $this->record->update([$generation->field => $generation->result]);
        }

        $generation->update(['applied_at' => now(), 'applied_by' => Auth::id()]);

        Notification::make()->success()->title('Applied to '.ActionRegistry::for($generation->field)['label'])->send();
    }

    public function restoreGeneration(int $id): void
    {
        $generation = AiGeneration::find($id);

        if (! $generation || ! $generation->canRestore()) {
            return;
        }

        if ($generation->field === 'alt_text') {
            $this->mediaForRecord()?->update(['alt_text' => $generation->input_snapshot]);
        } else {
            $this->record->update([$generation->field => $generation->input_snapshot]);
        }

        $generation->update(['restored_at' => now(), 'restored_by' => Auth::id()]);

        Notification::make()->success()->title('Restored previous value for '.ActionRegistry::for($generation->field)['label'])->send();
    }

    // پیشنهادهای لینک داخلیِ هوش‌مصنوعی را به همان جدول/چرخه‌ی موجود
    // (App\Models\InternalLinkSuggestion، تایید/رد در Internal Linking Center) اضافه می‌کند —
    // منطق insertLinkForSuggestion دست‌نخورده می‌ماند، اینجا فقط ردیف pending با origin=ai می‌سازد
    public function applyInternalLinkSuggestions(int $id): void
    {
        $generation = AiGeneration::find($id);

        if (! $generation || ! $generation->canApply() || $generation->field !== 'internal_links') {
            return;
        }

        $count = 0;

        foreach ($generation->result as $item) {
            $targetType = $item['type'] ?? null;
            $targetId = (int) ($item['id'] ?? 0);

            if (! in_array($targetType, ['Article', 'Page'], true) || $targetId === 0) {
                continue;
            }

            $target = $targetType === 'Article' ? Article::find($targetId) : PageModel::find($targetId);

            if (! $target) {
                continue;
            }

            InternalLinkSuggestion::updateOrCreate(
                [
                    'source_type' => $this->recordType,
                    'source_id' => $this->record->id,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                ],
                [
                    'locale' => $this->record->locale,
                    'confidence_score' => 70,
                    'recommended_anchor_text' => Str::limit($item['anchor_text'] ?? $target->title, 60, ''),
                    'reason' => $item['reason'] ?? 'Suggested by AI.',
                    'status' => 'pending',
                    'origin' => 'ai',
                ]
            );
            $count++;
        }

        $generation->update(['applied_at' => now(), 'applied_by' => Auth::id()]);

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
