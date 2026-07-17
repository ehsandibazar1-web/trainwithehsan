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
    // فقط این دو ارائه‌دهنده واقعاً یک API عمومیِ embedding دارند — Anthropic/Grok/DeepSeek ندارند
    // (نگاه کنید به CLAUDE.md، بخش RAG). App\Services\AiAssistant\ProviderManager::embed() فقط
    // برای این دو یک EmbeddingProvider واقعی می‌سازد.
    public const EMBEDDING_CAPABLE_SLUGS = ['openai', 'gemini'];

    // فقط این دو ارائه‌دهنده واقعاً یک API عمومیِ تولید تصویر دارند — Anthropic/Grok/DeepSeek
    // ندارند (Claude اصلاً تصویر تولید نمی‌کند). App\Services\AiAssistant\ProviderManager::generateImage()
    // فقط برای این دو یک ImageProvider واقعی می‌سازد — نگاه کنید به CLAUDE.md، بخش AI Image Pipeline.
    public const IMAGE_GENERATION_CAPABLE_SLUGS = ['openai', 'gemini'];

    protected $fillable = [
        'slug', 'name', 'api_key', 'base_url', 'default_model', 'embedding_model', 'image_model',
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

    // مثل isUsable، بعلاوه‌ی اینکه واقعاً یک ارائه‌دهنده‌ی embedding-capable باشد و مدل embedding
    // آن پر شده باشد — ProviderManager::resolveEmbeddingProvider() همین را چک می‌کند
    protected function isUsableForEmbeddings(): Attribute
    {
        return Attribute::get(fn () => $this->is_usable
            && in_array($this->slug, self::EMBEDDING_CAPABLE_SLUGS, true)
            && filled($this->embedding_model));
    }

    // مثل isUsableForEmbeddings، بعلاوه‌ی این‌که واقعاً یک ارائه‌دهنده‌ی image-generation-capable
    // باشد و مدل تصویرش پر شده باشد — ProviderManager::resolveImageProviderCandidates() همین را چک می‌کند
    protected function isUsableForImageGeneration(): Attribute
    {
        return Attribute::get(fn () => $this->is_usable
            && in_array($this->slug, self::IMAGE_GENERATION_CAPABLE_SLUGS, true)
            && filled($this->image_model));
    }
}
