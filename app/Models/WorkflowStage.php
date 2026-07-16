<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک مرحله‌ی خط‌تولید محتوا (Idea → ... → Archived) — قابل‌تنظیم توسط ادمین از طریق
 * WorkflowStageResource؛ هشت ردیف پیش‌فرض با migration seed می‌شوند. اسلاگ‌های پیش‌فرض
 * (STAGE_* ثابت‌های زیر) تنها نقاطی هستند که App\Models\ContentPlan::moveToStage() و
 * App\Console\Commands\PublishDueArticles برای یکپارچگی خودکار (materialize/sync) به آن‌ها
 * تکیه می‌کنند — مراحل سفارشی/دیگر همچنان کاملاً کار می‌کنند، فقط این یکپارچگی‌های خاص را ندارند.
 */
class WorkflowStage extends Model
{
    // اسلاگ‌های شناخته‌شده در کد — نگاه کنید به توضیح بالا
    public const STAGE_IDEA = 'idea';

    public const STAGE_AI_DRAFT = 'ai_draft';

    public const STAGE_HUMAN_REVIEW = 'human_review';

    public const STAGE_SEO_REVIEW = 'seo_review';

    public const STAGE_SCHEDULED = 'scheduled';

    public const STAGE_PUBLISHED = 'published';

    public const STAGE_ARCHIVED = 'archived';

    protected $fillable = [
        'slug', 'label', 'sort_order', 'color', 'is_default', 'is_terminal', 'checklist_items',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_terminal' => 'boolean',
            'checklist_items' => 'array',
        ];
    }

    public function contentPlans(): HasMany
    {
        return $this->hasMany(ContentPlan::class);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    // مرحله‌ای که ContentPlanهای تازه از آن شروع می‌کنند — اگر هیچ ردیفی is_default=true نداشت
    // (نصب دستکاری‌شده)، اولین مرحله بر اساس ترتیب استفاده می‌شود تا هرگز null برنگردد
    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::orderBy('sort_order')->first();
    }
}
