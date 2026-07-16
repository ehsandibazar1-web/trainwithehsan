<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * یک کارتِ برنامه‌ریز محتوا — از یک ایده‌ی محض (بدون Article/Page واقعی) تا انتشار نهایی.
 * contentable (Article/Page) تا رسیدن به مرحله‌ی AI Draft می‌تواند null باشد؛
 * materializeContent() همان‌جا رکورد واقعی را می‌سازد. هرگز منطق انتشار/زمان‌بندی موجود را
 * تکرار نمی‌کند — روی همان ستون‌های published_at/status می‌نویسد که EditorialCalendar/
 * PublishDueArticles همیشه از آن‌ها استفاده کرده‌اند.
 */
class ContentPlan extends Model
{
    use LogsActivity;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'title', 'locale', 'content_type', 'contentable_type', 'contentable_id',
        'category', 'workflow_stage_id', 'priority', 'author_id', 'assigned_to',
        'planned_publish_at', 'due_at', 'checklist_state', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_publish_at' => 'datetime',
            'due_at' => 'datetime',
            'checklist_state' => 'array',
        ];
    }

    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    public function contentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ContentTask::class)->orderBy('sort_order');
    }

    public function stageTransitions(): HasMany
    {
        return $this->hasMany(ContentPlanStageTransition::class)->latest('id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // تاریخ مؤثر انتشار برای نمایش در تقویم — اگر contentable واقعی وجود دارد، published_at
    // خودِ آن مرجع است (نه planned_publish_at این کارت)
    public function effectivePublishDate(): ?Carbon
    {
        return $this->contentable?->published_at ?? $this->planned_publish_at;
    }

    /**
     * تغییر مرحله + ثبت تاریخچه (برای آمار داشبورد) + مادیت‌بخشیِ خودکار (materialize) هنگام
     * رسیدن به AI Draft. هیچ اعلانی اینجا مستقیم فرستاده نمی‌شود — App\Observers یا فراخوان
     * بالادست مسئول آن است (نگاه کنید به CLAUDE.md، بخش سیستم اعلان).
     */
    public function moveToStage(WorkflowStage $stage, ?User $actor = null): void
    {
        if ($this->workflow_stage_id === $stage->id) {
            return;
        }

        $fromStageId = $this->workflow_stage_id;

        DB::transaction(function () use ($stage, $actor, $fromStageId) {
            $this->update(['workflow_stage_id' => $stage->id]);

            ContentPlanStageTransition::create([
                'content_plan_id' => $this->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $stage->id,
                'changed_by' => $actor?->id,
            ]);

            if ($stage->slug === WorkflowStage::STAGE_AI_DRAFT && ! $this->contentable_id) {
                $this->materializeContent();
            }
        });
    }

    /**
     * ساخت رکورد واقعی Article/Page از روی این ایده — فقط یک‌بار اجرا می‌شود (اگر contentable
     * از قبل وجود داشته باشد کاری نمی‌کند). عنوان/دسته/برچسب‌ها از کارت کپی می‌شود؛ بدنه خالی
     * می‌ماند تا دستیار هوش مصنوعی (یا خودِ ادمین) آن را در همان صفحه‌ی ویرایش تکمیل کند —
     * هرگز خودکار محتوا تولید نمی‌کند (طبق قاعده‌ی «تولید همیشه با کلیک صریح ادمین است»).
     */
    public function materializeContent(): Model
    {
        if ($this->contentable_id && $this->contentable) {
            return $this->contentable;
        }

        $type = $this->content_type ?: 'Article';
        $locale = $this->locale ?: 'en';
        $modelClass = $type === 'Page' ? Page::class : Article::class;
        $slug = $this->uniqueSlugFor($modelClass, $locale);

        $attributes = [
            'locale' => $locale,
            'title' => $this->title,
            'slug' => $slug,
            'body' => '',
            'status' => 'draft',
        ];

        if ($modelClass === Article::class) {
            $attributes['category'] = $this->category;
            $attributes['author_name'] = $this->author?->name ?? 'Ehsan Dibazar';
        }

        $record = $modelClass::create($attributes);

        $tagIds = $this->tags()->pluck('tags.id');
        if ($tagIds->isNotEmpty()) {
            $record->tags()->sync($tagIds);
        }

        $this->update([
            'content_type' => $type,
            'contentable_type' => $type,
            'contentable_id' => $record->id,
        ]);

        return $record;
    }

    private function uniqueSlugFor(string $modelClass, string $locale): string
    {
        $base = Str::slug($this->title) ?: 'untitled-'.Str::random(6);
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'workflow_stage_id', 'priority', 'assigned_to', 'planned_publish_at', 'due_at'])
            ->logOnlyDirty()
            ->useLogName('content_plan')
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Content plan created',
                'updated' => 'Content plan updated',
                'deleted' => 'Content plan deleted',
                default => $eventName,
            });
    }
}
