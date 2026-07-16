<?php

namespace App\Services\AiAssistant\Support;

/**
 * پیکربندی زمان‌اجرای یک اتصال به ارائه‌دهنده — App\Services\AiAssistant\ProviderManager این را
 * از یک App\Models\AiProviderConfig (کلید رمزگشایی‌شده + تنظیمات) می‌سازد و به سازنده‌ی کلاس
 * Provider مربوطه می‌دهد. یک شیء ساده و بدون رفتار (value object) — هیچ منطقی اینجا نیست.
 */
final class ProviderCredentials
{
    public function __construct(
        public readonly ?string $apiKey,
        public readonly ?string $baseUrl = null,
        public readonly ?string $model = null,
        public readonly ?int $maxTokens = null,
        public readonly ?float $temperature = null,
        public readonly ?int $timeoutSeconds = null,
    ) {}
}
