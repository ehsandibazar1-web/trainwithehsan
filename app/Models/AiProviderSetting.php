<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تنظیمات سراسری تک‌ردیفه‌ی Provider Manager (همیشه فقط یک ردیف، id=1 — ساخته‌شده توسط
 * migration seed). `current()` تنها نقطه‌ی خواندن این تنظیمات در سراسر کد است.
 */
class AiProviderSetting extends Model
{
    protected $fillable = [
        'default_provider_config_id', 'failover_enabled', 'fallback_provider_config_id',
        'embedding_provider_config_id',
        'default_image_provider_config_id', 'image_failover_enabled', 'fallback_image_provider_config_id',
    ];

    protected function casts(): array
    {
        return [
            'failover_enabled' => 'boolean',
            'image_failover_enabled' => 'boolean',
        ];
    }

    public function defaultProvider(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'default_provider_config_id');
    }

    public function fallbackProvider(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'fallback_provider_config_id');
    }

    public function embeddingProvider(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'embedding_provider_config_id');
    }

    public function defaultImageProvider(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'default_image_provider_config_id');
    }

    public function fallbackImageProvider(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'fallback_image_provider_config_id');
    }

    // تک ردیف تنظیمات را برمی‌گرداند — اگر به هر دلیلی (مثلاً یک نصب خیلی قدیمی بدون seed) وجود
    // نداشت، یک ردیف خالی می‌سازد به‌جای اینکه ProviderManager با یک null کار کند
    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }
}
