<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک یافته‌ی AI Agent — نگاه کنید به App\Services\AiAgent\AgentAuditService (تشخیص) و
 * App\Services\AiAgent\AgentFixService (صف‌کردن/تایید/رد رفع). content_type/content_id عمداً
 * یک رابطه‌ی morphTo واقعی نیست — بعضی دسته‌ها (Blog index) به هیچ رکورد Eloquent واقعی اشاره
 * نمی‌کنند، هم‌روح SeoAuditService::finding() که همین شکل رشته‌ای را دارد.
 */
class AiRecommendation extends Model
{
    protected $fillable = [
        'audit_run_id', 'category', 'content_type', 'content_id',
        'related_content_type', 'related_content_id', 'locale',
        'severity', 'title', 'detail', 'edit_url', 'related_edit_url',
        'fix_type', 'fix_field', 'fix_mode', 'ai_generation_id',
        'status', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function auditRun(): BelongsTo
    {
        return $this->belongsTo(AiAuditRun::class, 'audit_run_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isFixable(): bool
    {
        return $this->fix_type !== null;
    }

    // رکورد واقعی Article/Page متناظر (اگر این یافته اصلا به یک رکورد اشاره کند) — برای دسته‌هایی
    // مثل «Blog index» که content_type='' است، همیشه null برمی‌گردد
    public function resolveContentRecord(): ?Model
    {
        return match ($this->content_type) {
            'Article' => Article::find($this->content_id),
            'Page' => Page::find($this->content_id),
            default => null,
        };
    }
}
