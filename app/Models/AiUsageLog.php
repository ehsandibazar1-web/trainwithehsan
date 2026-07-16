<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف لاگ برای هر تماس با ProviderManager::respond() — موفق یا ناموفق. عمداً denormalized
 * است (`provider_slug`/`model` رشته‌ی خام، نه کلید خارجی) تا حذف بعدیِ یک ai_provider_config
 * تاریخچه‌ی مصرف را از بین نبرد. هرگز کلید API یا محتوای کامل پرامپت/پاسخ اینجا ذخیره نمی‌شود —
 * فقط شمار توکن/هزینه/زمان، طبق الزام امنیتی صریح («Never log API keys»).
 */
class AiUsageLog extends Model
{
    protected $fillable = [
        'provider_slug', 'model', 'action_key', 'content_type', 'content_id',
        'prompt_tokens', 'completion_tokens', 'total_tokens', 'estimated_cost_usd',
        'response_time_ms', 'status', 'error_message', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost_usd' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForProvider($query, string $providerSlug)
    {
        return $query->where('provider_slug', $providerSlug);
    }

    public function scopeForAction($query, string $actionKey)
    {
        return $query->where('action_key', $actionKey);
    }
}
