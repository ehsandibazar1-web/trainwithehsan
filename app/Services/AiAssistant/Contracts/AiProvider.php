<?php

namespace App\Services\AiAssistant\Contracts;

/**
 * قرارداد یکسان برای هر ارائه‌دهنده‌ی هوش مصنوعی — App\Services\AiAssistant\ContentAssistantService
 * فقط با این اینترفیس کار می‌کند، هیچ‌جای دیگری مستقیماً به Anthropic/OpenAI/... وابسته نیست.
 * افزودن ارائه‌دهنده‌ی جدید = یک کلاس تازه + یک شاخه‌ی match در AppServiceProvider::register().
 */
interface AiProvider
{
    /**
     * @param  string[]  $images  آدرس‌های عمومی تصویر — فقط ارائه‌دهنده‌های دارای قابلیت بینایی
     *                            از آن استفاده می‌کنند؛ بقیه نادیده می‌گیرند
     * @param  array<string, mixed>  $options
     *
     * @throws \RuntimeException در صورت خطای شبکه/API یا نبود پیکربندی
     */
    public function respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string;
}
