<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تنظیمات اتصال یک ارائه‌دهنده‌ی هوش مصنوعی (Anthropic/OpenAI/Gemini/Grok/DeepSeek/...) — پنج
 * ردیف اولیه با migration seed می‌شوند (غیرفعال، بدون کلید). `api_key` با کست `encrypted`
 * ذخیره می‌شود — اولین استفاده‌ی رمزنگاری در این پروژه؛ `App\Filament\Pages\AiProviderSettings`
 * هرگز مقدار رمزگشایی‌شده را در فرم پر نمی‌کند، فقط اگر فیلد جدید تایپ شود آن را جایگزین می‌کند.
 * `App\Services\AiAssistant\ProviderManager` تنها مصرف‌کننده‌ی `api_key`ی رمزگشایی‌شده است.
 */
class AiProviderConfig extends Model
{
    protected $fillable = [
        'slug', 'name', 'api_key', 'base_url', 'default_model',
        'max_tokens', 'temperature', 'timeout_seconds', 'is_enabled',
        'last_tested_at', 'last_test_status', 'last_test_latency_ms', 'last_test_model', 'last_test_error',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'temperature' => 'decimal:2',
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiProviderModel::class);
    }

    // کلید API را «آماده‌ی استفاده»ی واقعی نشان می‌دهد — فعال است و مقدار غیرخالی دارد؛
    // ProviderManager قبل از انتخاب یک ارائه‌دهنده از دیتابیس همین را چک می‌کند
    protected function isUsable(): Attribute
    {
        return Attribute::get(fn () => $this->is_enabled && filled($this->api_key));
    }
}
