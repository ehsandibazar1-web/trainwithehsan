<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * یک اجرای تولید/بهبود محتوا توسط دستیار هوش مصنوعی برای یک فیلد مشخص روی یک Article/Page —
 * صف‌شده (App\Jobs\RunAiContentGeneration)، هرگز به‌صورت خودکار روی رکورد نمی‌نویسد؛ Apply/Restore
 * هر دو عملیات دستی ادمین‌اند که از همان مسیر Eloquent update() عبور می‌کنند.
 */
class AiGeneration extends Model
{
    protected $fillable = [
        'user_id', 'content_type', 'content_id', 'field', 'mode', 'provider', 'status',
        'input_snapshot', 'result', 'error', 'applied_at', 'applied_by', 'restored_at', 'restored_by',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'result' => 'array',
        'applied_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function scopeForField($query, string $contentType, int $contentId, string $field)
    {
        return $query->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('field', $field);
    }

    public function canApply(): bool
    {
        return $this->status === 'completed' && $this->result !== null;
    }

    public function canRestore(): bool
    {
        // input_snapshot می‌تواند خودش null باشد (یعنی مقدار اصلی خالی بوده) — این یک بازگردانی
        // معتبر است، پس شرط را روی applied_at/restored_at می‌گذاریم، نه روی مقدار خود snapshot
        return $this->applied_at !== null && $this->restored_at === null;
    }
}
