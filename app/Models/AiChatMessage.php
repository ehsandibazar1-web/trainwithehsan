<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک پیام در گفتگوی هوش مصنوعیِ یک Article/Page مشخص — user یا assistant. اگر پیامِ کاربر به یک
 * درخواست عملی (تولید/بهبود یک فیلد) طبقه‌بندی شود، ردیف assistant متناظر به همان AiGeneration
 * ساخته‌شده لینک می‌شود (related_generation_id) تا وضعیت/نتیجه‌اش داخل رشته‌ی گفتگو دیده شود.
 */
class AiChatMessage extends Model
{
    protected $fillable = [
        'content_type', 'content_id', 'role', 'message', 'related_generation_id',
    ];

    public function relatedGeneration(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'related_generation_id');
    }

    public function scopeForRecord($query, string $contentType, int $contentId)
    {
        return $query->where('content_type', $contentType)->where('content_id', $contentId);
    }
}
