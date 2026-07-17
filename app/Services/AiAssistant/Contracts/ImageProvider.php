<?php

namespace App\Services\AiAssistant\Contracts;

/**
 * قرارداد جداگانه از AiProvider/EmbeddingProvider — فقط ارائه‌دهنده‌هایی که واقعاً یک API عمومیِ
 * تولید تصویر دارند (امروز: OpenAI و Gemini — Anthropic/Grok/DeepSeek ندارند، Claude اصلاً تصویر
 * تولید نمی‌کند) این را هم پیاده‌سازی می‌کنند، علاوه بر AiProvider خودشان.
 * App\Services\AiAssistant\ProviderManager::generateImage() تنها مصرف‌کننده است.
 */
interface ImageProvider
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{bytes: string, mime_type: string, revised_prompt: ?string}
     */
    public function generateImage(string $prompt, array $options = []): array;

    /** @return ?array{prompt_tokens: ?int, completion_tokens: ?int} */
    public function lastImageUsage(): ?array;
}
