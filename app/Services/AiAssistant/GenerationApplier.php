<?php

namespace App\Services\AiAssistant;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * مسیر نوشتنِ مشترکِ نتیجه‌ی یک AiGeneration روی رکورد — استخراج‌شده از App\Livewire\AiAssistantPanel
 * (که پیش از این تنها فراخوان بود) تا App\Services\AiAgent (داشبورد AI Agent) هم بتواند دقیقاً همان
 * منطق نوشتن را صدا بزند، بدون تکرار. طبق قانون این پروژه («تولید هرگز خودش نمی‌نویسد») این کلاس
 * فقط از جایی صدا زده می‌شود که کاربر صریحاً روی Apply/Approve کلیک کرده — نگاه کنید به CLAUDE.md
 * بخش «Must Never Be Changed».
 */
class GenerationApplier
{
    public function apply(AiGeneration $generation, Model $record): bool
    {
        if (! $generation->canApply()) {
            return false;
        }

        if ($generation->field === 'alt_text') {
            $media = Media::forRecord($record);

            if (! $media) {
                return false;
            }

            $media->update(['alt_text' => $generation->result]);
        } else {
            $record->update([$generation->field => $generation->result]);
        }

        $generation->update(['applied_at' => now(), 'applied_by' => auth()->id()]);

        return true;
    }

    public function restore(AiGeneration $generation, Model $record): bool
    {
        if (! $generation->canRestore()) {
            return false;
        }

        if ($generation->field === 'alt_text') {
            Media::forRecord($record)?->update(['alt_text' => $generation->input_snapshot]);
        } else {
            $record->update([$generation->field => $generation->input_snapshot]);
        }

        $generation->update(['restored_at' => now(), 'restored_by' => auth()->id()]);

        return true;
    }

    // پیشنهادهای لینک داخلیِ هوش‌مصنوعی را به همان جدول/چرخه‌ی موجود
    // (App\Models\InternalLinkSuggestion، تایید/رد در Internal Linking Center) اضافه می‌کند —
    // منطق insertLinkForSuggestion دست‌نخورده می‌ماند، اینجا فقط ردیف pending با origin=ai می‌سازد
    public function applyInternalLinkSuggestions(AiGeneration $generation, Model $sourceRecord): int
    {
        if (! $generation->canApply() || $generation->field !== 'internal_links') {
            return 0;
        }

        $sourceType = $sourceRecord instanceof Article ? 'Article' : 'Page';
        $count = 0;

        foreach ($generation->result as $item) {
            $targetType = $item['type'] ?? null;
            $targetId = (int) ($item['id'] ?? 0);

            if (! in_array($targetType, ['Article', 'Page'], true) || $targetId === 0) {
                continue;
            }

            $target = $targetType === 'Article' ? Article::find($targetId) : Page::find($targetId);

            if (! $target) {
                continue;
            }

            InternalLinkSuggestion::updateOrCreate(
                [
                    'source_type' => $sourceType,
                    'source_id' => $sourceRecord->id,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                ],
                [
                    'locale' => $sourceRecord->locale,
                    'confidence_score' => 70,
                    'recommended_anchor_text' => Str::limit($item['anchor_text'] ?? $target->title, 60, ''),
                    'reason' => $item['reason'] ?? 'Suggested by AI.',
                    'status' => 'pending',
                    'origin' => 'ai',
                ]
            );
            $count++;
        }

        $generation->update(['applied_at' => now(), 'applied_by' => auth()->id()]);

        return $count;
    }
}
