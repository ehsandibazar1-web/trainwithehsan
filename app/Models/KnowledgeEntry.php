<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * یک واحد دانش ساخت‌یافته درباره‌ی برند/کسب‌وکار (بیوگرافی، خدمات، سیاست‌ها، مکان‌ها، ...) که
 * App\Services\KnowledgeBase\KnowledgeBaseService قبل از هر تولید محتوا، فقط ورودی‌های واقعاً
 * مرتبط را بازیابی و به پرامپت اضافه می‌کند — نه یک بلوک ثابت مثل Brand Memory. تاریخچه‌ی نسخه‌ها
 * از همان مکانیزم LogsActivity که Article استفاده می‌کند (نمایش در ActivityLogPage موجود، بدون
 * UI تازه).
 */
class KnowledgeEntry extends Model
{
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'title', 'category', 'locale', 'content', 'source',
        'status', 'priority', 'is_pinned', 'expires_at',
    ];

    // مقادیر پیش‌فرض در سطح مدل (نه فقط ستون دیتابیس) تا بلافاصله بعد از create() هم روی
    // نمونه‌ی حافظه‌ای در دسترس باشند، بدون نیاز به fresh() اضافی
    protected $attributes = [
        'locale' => 'en',
        'status' => self::STATUS_ACTIVE,
        'priority' => self::PRIORITY_MEDIUM,
        'is_pinned' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(KnowledgeEntryAttachment::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function generations(): BelongsToMany
    {
        return $this->belongsToMany(AiGeneration::class, 'ai_generation_knowledge_entry');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->notExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'category', 'locale', 'content', 'status', 'priority'])
            ->logOnlyDirty()
            ->useLogName('knowledge_entry');
    }
}
