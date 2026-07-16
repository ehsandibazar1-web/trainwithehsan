<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * override ارائه‌دهنده/مدل برای یک فیلد مشخص از App\Services\AiAssistant\ActionRegistry
 * (seo_title, faq, translate, ...) — نبودِ ردیف برای یک کلید یعنی «همیشه ارائه‌دهنده‌ی
 * پیش‌فرض» (App\Models\AiProviderSetting::default_provider_config_id). این جدول عمداً
 * دانه‌ریز (per-field) است، نه دسته‌بندی‌شده — مثلاً seo_title/meta_description/og_title/
 * og_description هرکدام override مستقل خودشان را دارند، حتی اگر در UI زیر یک سرتیتر «SEO»
 * گروه‌بندی نشان داده شوند.
 */
class AiActionOverride extends Model
{
    protected $fillable = ['action_key', 'ai_provider_config_id', 'model'];

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'ai_provider_config_id');
    }
}
