<?php

namespace App\Services\BrandMemory;

use App\Models\BrandMemorySection;
use Illuminate\Support\Str;

/**
 * تنها جای ترکیب حافظه‌ی برند به یک بلوک متنی برای پرامپت — App\Services\AiAssistant\ContentAssistantService
 * این را در هر سه سازنده‌ی system prompt خودش صدا می‌زند (نگاه کنید به CLAUDE.md)، پس هیچ فراخوان
 * دیگری در کدبیس مستقیماً این منطق را تکرار نمی‌کند. اگر هیچ بخشی فعال/پرمحتوا نباشد یک رشته‌ی
 * خالی برمی‌گرداند — یعنی نصب‌های بدون پیکربندی حافظه‌ی برند دقیقاً همان رفتار قبل از این فیچر را
 * دارند (بدون هیچ بلوک اضافه‌ای در پرامپت).
 */
class BrandMemoryService
{
    public function hasContent(): bool
    {
        return $this->buildContext() !== '';
    }

    /**
     * زبان مقدار محتوا (fa شامل می‌شود) را می‌سازد — اگر مقدار به همان $locale خالی بود، به en
     * برمی‌گردد (زبان مرجع پیش‌فرض این فیچر)؛ اگر آن هم خالی بود، آن بخش کلاً از پرامپت حذف
     * می‌شود (بدون برچسبِ خالی نشان دادن — همان روحیه‌ی «null به‌جای حدس زدن» در بقیه‌ی پروژه).
     */
    public function buildContext(string $locale = 'en'): string
    {
        $sections = BrandMemorySection::query()
            ->where('is_enabled', true)
            ->with('values')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group');

        $blocks = [];

        foreach ($sections as $group => $items) {
            $lines = [];

            foreach ($items as $section) {
                $content = $this->resolveContent($section, $locale);

                if ($content !== null) {
                    $lines[] = "- {$section->label}: {$content}";
                }
            }

            if ($lines !== []) {
                $blocks[] = "## {$group}\n".implode("\n", $lines);
            }
        }

        if ($blocks === []) {
            return '';
        }

        return "BRAND MEMORY — permanent brand knowledge. Follow this consistently in every response:\n\n"
            .implode("\n\n", $blocks);
    }

    private function resolveContent(BrandMemorySection $section, string $locale): ?string
    {
        $value = $section->valueFor($locale) ?? $section->valueFor('en');
        $content = $value?->content;

        return filled($content) ? Str::of($content)->trim()->toString() : null;
    }
}
