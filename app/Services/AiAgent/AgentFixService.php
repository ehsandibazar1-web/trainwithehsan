<?php

namespace App\Services\AiAgent;

use App\Jobs\RunAiContentGeneration;
use App\Jobs\TranslateArticleDraft;
use App\Models\AiGeneration;
use App\Models\AiRecommendation;
use App\Models\Media;
use App\Services\AiAssistant\GenerationApplier;
use Illuminate\Support\Facades\Auth;

/**
 * چرخه‌ی «رفعِ یک‌کلیکی» یک App\Models\AiRecommendation — صف‌کردن/تایید/رد. هیچ مسیر تولید یا
 * نوشتنِ تازه‌ای نمی‌سازد: دقیقاً همان صف‌کردنِ AiGeneration که App\Livewire\AiAssistantPanel
 * برای فیلدهای معمولی/لینک‌داخلی/ترجمه انجام می‌دهد را دوباره صدا می‌زند، و نوشتنِ نهایی را به
 * همان App\Services\AiAssistant\GenerationApplier می‌سپارد که آن کامپوننت هم استفاده می‌کند —
 * نگاه کنید به CLAUDE.md «Must Never Be Changed»: تولید هرگز خودش نمی‌نویسد، فقط کلیک صریح ادمین.
 */
class AgentFixService
{
    public function __construct(private readonly GenerationApplier $applier) {}

    /**
     * «Preview Fix» — تولید را صف می‌کند، چیزی روی رکورد نمی‌نویسد. false یعنی این یافته اصلا
     * رفع‌پذیر نیست (fix_type=null) یا رکورد مقصد دیگر وجود ندارد.
     */
    public function queueFix(AiRecommendation $recommendation): bool
    {
        if (! $recommendation->isFixable()) {
            return false;
        }

        $record = $recommendation->resolveContentRecord();
        if (! $record) {
            return false;
        }

        if ($recommendation->fix_type === 'translate') {
            $generation = AiGeneration::create([
                'user_id' => Auth::id(),
                'content_type' => $recommendation->content_type,
                'content_id' => $recommendation->content_id,
                'field' => 'translate',
                'mode' => $recommendation->fix_mode,
                'provider' => config('services.anthropic.driver', 'anthropic'),
                'status' => 'queued',
            ]);

            TranslateArticleDraft::dispatch($recommendation->content_type, $recommendation->content_id, $recommendation->fix_mode, $generation->id);
        } else {
            $inputSnapshot = $recommendation->fix_field === 'alt_text'
                ? Media::forRecord($record)?->alt_text
                : $record->getAttribute($recommendation->fix_field);

            $generation = AiGeneration::create([
                'user_id' => Auth::id(),
                'content_type' => $recommendation->content_type,
                'content_id' => $recommendation->content_id,
                'field' => $recommendation->fix_field,
                'mode' => $recommendation->fix_mode,
                'provider' => config('services.anthropic.driver', 'anthropic'),
                'status' => 'queued',
                'input_snapshot' => $inputSnapshot,
            ]);

            RunAiContentGeneration::dispatch($generation->id);
        }

        $recommendation->update(['ai_generation_id' => $generation->id]);

        return true;
    }

    /**
     * «Approve» — فقط بعد از اینکه تولیدِ صف‌شده کامل شده باشد کار می‌کند. برای fix_type=translate
     * چیزی برای نوشتن نیست (App\Jobs\TranslateArticleDraft خودش پیش‌نویسِ واقعی را ساخته)، فقط
     * یافته را resolved علامت می‌زند.
     */
    public function approveFix(AiRecommendation $recommendation): bool
    {
        $generation = $recommendation->generation;

        if (! $generation || $generation->status !== 'completed') {
            return false;
        }

        if ($recommendation->fix_type === 'field') {
            $record = $recommendation->resolveContentRecord();
            if (! $record || ! $this->applier->apply($generation, $record)) {
                return false;
            }
        } elseif ($recommendation->fix_type === 'internal_links') {
            $record = $recommendation->resolveContentRecord();
            if (! $record) {
                return false;
            }
            $this->applier->applyInternalLinkSuggestions($generation, $record);
        }
        // fix_type === 'translate': نتیجه از قبل یک رکورد جداگانه‌ی ذخیره‌شده است، چیزی برای اعمال نیست

        $recommendation->update([
            'status' => 'applied',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        return true;
    }

    /**
     * «Reject» — برای یافته‌های فقط-گزارشی هم کار می‌کند (dismiss)؛ نیازی به تولید صف‌شده ندارد.
     */
    public function rejectFix(AiRecommendation $recommendation): void
    {
        $recommendation->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);
    }
}
