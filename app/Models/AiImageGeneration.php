<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک تلاشِ «Generate Hero Image» برای یک Article/Page — هم‌شکل و هم‌روحِ AiGeneration (وضعیت
 * queued/processing/completed/failed/cancelled، content_type/content_id با همان قرارداد
 * short-string morph) اما جدولی جداست چون خروجی‌اش یک فایل باینری/رکورد Media است، نه متن یا
 * آرایه‌ای که ContentAssistantService::parseResponse() بفهمد. App\Jobs\GenerateHeroImage تنها
 * نویسنده‌ی این جدول است؛ برخلاف AiGeneration، اینجا هیچ «Apply» جداگانه‌ای وجود ندارد — تکمیل
 * موفق یعنی تصویر از قبل ذخیره و featured image تنظیم شده (هم‌روحِ App\Jobs\TranslateArticleDraft،
 * که «تولید» و «ساختن رکورد واقعی» یک عمل هستند، نه یک پیش‌نمایش دوقدمی).
 */
class AiImageGeneration extends Model
{
    protected $fillable = [
        'content_type', 'content_id', 'prompt_field', 'prompt_used', 'status',
        'provider_slug', 'model', 'media_id', 'error', 'user_id',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForRecord($query, string $contentType, int $contentId)
    {
        return $query->where('content_type', $contentType)->where('content_id', $contentId);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['queued', 'processing'], true);
    }
}
